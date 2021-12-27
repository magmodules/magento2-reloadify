<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Controller\Cart;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Class Restore
 */
class Restore extends Action
{
    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * Restore constructor.
     *
     * @param Context $context
     * @param EncryptorInterface $encryptor
     * @param CartRepositoryInterface $quoteRepository
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor,
        CartRepositoryInterface $quoteRepository,
        Session $checkoutSession
    ) {
        $this->encryptor = $encryptor;
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $encryptedQuoteId = $this->getRequest()->getParam('id');
        if ($encryptedQuoteId) {
            try {
                $quoteId = $this->encryptor->decrypt($encryptedQuoteId);
                $quote = $this->quoteRepository->get($quoteId);
                $this->checkoutSession->replaceQuote($quote);
                return $this->_redirect('checkout/cart/index');
            } catch (NoSuchEntityException $e) {
                return $this->_redirect('cms/index/index');
            }
        }
        return $this->_redirect('cms/index/index');
    }
}
