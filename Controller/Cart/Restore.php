<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Controller\Cart;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magmodules\Reloadify\Service\Cart\QuoteRestorer;

/**
 * Restore previously abandoned quote into checkout session.
 */
class Restore extends Action
{
    public function __construct(
        Context $context,
        private readonly QuoteRestorer $quoteRestorer,
        private readonly CustomerSession $customerSession
    ) {
        parent::__construct($context);
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
            $quote = $this->quoteRestorer->load((string)$encryptedQuoteId);

            if (!$quote->getIsActive() && $this->quoteRestorer->isQuoteConvertedToOrder((int)$quote->getId())) {
                $this->messageManager->addNoticeMessage(__('An order has already been placed for this quote.'));
                return $this->redirectToHome($resultRedirect);
            }

            $this->quoteRestorer->restore($quote, (int)$this->customerSession->getCustomerId());

            return $resultRedirect->setPath('checkout/cart/index');

        } catch (NoSuchEntityException $e) {
            $this->messageManager->addNoticeMessage(__('The quote does not exist.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Unable to restore the cart at this time.'));
        }

        return $this->redirectToHome($resultRedirect);
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
