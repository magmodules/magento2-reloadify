<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Plugin\Customer;

use Magento\Customer\Model\AccountManagement as Subject;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * SaveEmail Plugin
 * Save guest email to quote after customer first time fill it on shipping step (needed for abandoned carts)
 */
class SaveEmail
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * SaveEmail constructor.
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Save email to quote
     *
     * @param Subject $subject
     * @param bool $result
     * @param string $customerEmail
     * @param null $websiteId
     * @return bool
     */
    public function afterIsEmailAvailable(
        Subject $subject,
        bool $result,
        string $customerEmail
    ): bool {
        try {
            $quote = $this->checkoutSession->getQuote();
            $quote->setCustomerEmail($customerEmail);
            $this->quoteRepository->save($quote);
        } catch (\Exception $e) {
            return $result;
        }

        return $result;
    }
}
