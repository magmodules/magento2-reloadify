<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Controller\Cart;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Restore previously abandoned quote into checkout session.
 */
class Restore extends Action
{
    private EncryptorInterface $encryptor;
    private CartRepositoryInterface $quoteRepository;
    private CheckoutSession $checkoutSession;
    private OrderCollectionFactory $orderCollectionFactory;

    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        CartRepositoryInterface $quoteRepository,
        CheckoutSession $checkoutSession,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $encryptedQuoteId = $this->getRequest()->getParam('id');

        if (!$encryptedQuoteId) {
            return $this->redirectToHome($resultRedirect);
        }

        try {
            $quoteId = $this->encryptor->decrypt($encryptedQuoteId);
            $quote = $this->quoteRepository->get((int)$quoteId);

            if (!$quote->getIsActive()) {
                if ($this->isQuoteConvertedToOrder((int)$quoteId)) {
                    $this->messageManager->addNoticeMessage(__('An order has already been placed for this quote.'));
                    return $this->redirectToHome($resultRedirect);
                }

                $quote->setIsActive(true);
                $this->quoteRepository->save($quote);
            }

            $this->checkoutSession->replaceQuote($quote);
            return $resultRedirect->setPath('checkout/cart/index');

        } catch (NoSuchEntityException $e) {
            $this->messageManager->addNoticeMessage(__('The quote does not exist.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to restore the cart at this time.'));
        }

        return $this->redirectToHome($resultRedirect);
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

    /**
     * Redirects to homepage.
     *
     * @param Redirect $resultRedirect
     * @return ResultInterface
     */
    private function redirectToHome(Redirect $resultRedirect): ResultInterface
    {
        return $resultRedirect->setPath('cms/index/index');
    }

}
