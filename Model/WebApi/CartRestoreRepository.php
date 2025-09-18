<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\WebApi;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magmodules\Reloadify\Api\WebApi\CartRestoreInterface;

class CartRestoreRepository implements CartRestoreInterface
{
    private EncryptorInterface $encryptor;
    private CartRepositoryInterface $quoteRepository;
    private CheckoutSession $checkoutSession;
    private OrderCollectionFactory $orderCollectionFactory;

    public function __construct(
        EncryptorInterface $encryptor,
        CartRepositoryInterface $quoteRepository,
        CheckoutSession $checkoutSession,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->encryptor = $encryptor;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    public function restore(string $encryptedId): CartInterface
    {
        $quoteId = $this->encryptor->decrypt($encryptedId);
        $quote = $this->quoteRepository->get($quoteId);
        if (!$quote->getIsActive() && !$this->isQuoteConvertedToOrder((int)$quoteId)) {
            $quote->setIsActive(true);
            $this->quoteRepository->save($quote);
        }
        $this->checkoutSession->replaceQuote($quote);

        return $quote;
    }

    /**
     * Checks if a quote was already converted to an order.
     *
     * @param int $quoteId
     * @return bool
     */
    private function isQuoteConvertedToOrder(int $quoteId): bool
    {
        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quoteId);

        return $collection->getSize() > 0;
    }
}
