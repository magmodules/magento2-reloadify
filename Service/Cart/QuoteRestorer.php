<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Shared logic for restoring an abandoned quote into the checkout session.
 *
 * A quote that belongs to a customer account can not be used by a logged out visitor. The cart page
 * picks its REST endpoints from the session login state, so a logged out visitor always calls
 * /V1/guest-carts/:cartId/*. Those endpoints resolve the cart through quote_id_mask and, since the
 * APSB26-73 security patch, reject any quote that still carries a customer_id.
 *
 * Whenever the quote owner and the current session identity differ, the quote is therefore copied
 * into a new quote that matches the session. The original quote is closed: a customer who logs in
 * after opening the link would otherwise have the copy merged into the original and end up with
 * every product twice.
 */
class QuoteRestorer
{
    /**
     * @param EncryptorInterface $encryptor
     * @param CartRepositoryInterface $quoteRepository
     * @param CheckoutSession $checkoutSession
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param QuoteFactory $quoteFactory
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteResource $quoteResource
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly QuoteResource $quoteResource,
        private readonly QuoteIdMaskResource $quoteIdMaskResource
    ) {
    }

    /**
     * Load a quote by its encrypted ID.
     *
     * @param string $encryptedId
     * @return CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function load(string $encryptedId): CartInterface
    {
        return $this->quoteRepository->get((int)$this->encryptor->decrypt($encryptedId));
    }

    /**
     * Checks if a quote was already converted to an order.
     *
     * @param int $quoteId
     * @return bool
     */
    public function isQuoteConvertedToOrder(int $quoteId): bool
    {
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quoteId);

        return $collection->getSize() > 0;
    }

    /**
     * Place the quote in the checkout session, copying it first when it does not match the session.
     *
     * @param CartInterface $quote
     * @param int|null $currentCustomerId Customer ID of the active session, 0 or null when logged out.
     * @return CartInterface The quote that ended up in the session.
     */
    public function restore(CartInterface $quote, ?int $currentCustomerId = null): CartInterface
    {
        if (!$quote->getIsActive() && !$this->isQuoteConvertedToOrder((int)$quote->getId())) {
            $this->setIsActive($quote, true);
        }

        $sessionQuote = $this->resolveQuoteForSession($quote, (int)$currentCustomerId);
        $this->checkoutSession->replaceQuote($sessionQuote);

        return $sessionQuote;
    }

    /**
     * Return the quote itself when it already matches the session, a copy of it otherwise.
     *
     * @param CartInterface $quote
     * @param int $currentCustomerId
     * @return CartInterface
     */
    private function resolveQuoteForSession(CartInterface $quote, int $currentCustomerId): CartInterface
    {
        $ownerId = $quote instanceof Quote
            ? (int)$quote->getCustomerId()
            : (int)$quote->getCustomer()->getId();
        if ($ownerId === $currentCustomerId) {
            return $quote;
        }

        return $this->copyQuote($quote, $currentCustomerId);
    }

    /**
     * Copy the items of a quote into a new quote owned by the current session.
     *
     * Only the items and the coupon are carried over. Customer details and addresses are
     * deliberately left behind so that a forwarded recovery link can not expose them.
     *
     * @param CartInterface $source
     * @param int $customerId
     * @return CartInterface
     */
    private function copyQuote(CartInterface $source, int $customerId): CartInterface
    {
        /** @var Quote $source */
        /** @var Quote $copy */
        $copy = $this->quoteFactory->create();
        $copy->setStoreId($source->getStoreId());
        $copy->setIsActive(true);

        if ($customerId > 0) {
            $copy->setCustomer($this->customerRepository->getById($customerId));
            $copy->setCustomerIsGuest(false);
        } else {
            $copy->setCustomerId(null);
            $copy->setCustomerIsGuest(true);
        }

        $copy->merge($source);

        if ($source->getCouponCode()) {
            $copy->setCouponCode($source->getCouponCode());
        }

        $copy->collectTotals();
        $this->quoteRepository->save($copy);

        $this->setIsActive($source, false);

        if ($customerId === 0) {
            $this->quoteIdMaskResource->save($this->quoteIdMaskFactory->create()->setQuoteId($copy->getId()));
        }

        return $this->quoteRepository->get((int)$copy->getId());
    }

    /**
     * Change only the active state of a quote.
     *
     * The quote may belong to a customer other than the caller. Saving it through the repository is then
     * rejected by the AccessChangeQuoteControl plugin on REST requests, so the flag is written directly.
     *
     * @param CartInterface $quote
     * @param bool $isActive
     * @return void
     */
    private function setIsActive(CartInterface $quote, bool $isActive): void
    {
        $quote->setIsActive($isActive);
        $this->quoteResource->getConnection()->update(
            $this->quoteResource->getMainTable(),
            ['is_active' => (int)$isActive],
            ['entity_id = ?' => (int)$quote->getId()]
        );
    }
}
