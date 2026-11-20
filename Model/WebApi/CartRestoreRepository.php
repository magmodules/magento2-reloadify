<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\WebApi;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magmodules\Reloadify\Api\WebApi\CartRestoreInterface;
use Magmodules\Reloadify\Service\Cart\QuoteRestorer;

class CartRestoreRepository implements CartRestoreInterface
{
    public function __construct(
        private readonly QuoteRestorer $quoteRestorer,
        private readonly UserContextInterface $userContext
    ) {
    }

    /**
     * @inheritdoc
     */
    public function restore(string $encryptedId): CartInterface
    {
        $quote = $this->quoteRestorer->load($encryptedId);

        if (!$quote->getIsActive() && $this->quoteRestorer->isQuoteConvertedToOrder((int)$quote->getId())) {
            throw new LocalizedException(__('An order has already been placed for this quote.'));
        }

        return $this->quoteRestorer->restore($quote, $this->getCurrentCustomerId());
    }

    /**
     * Customer ID behind the current request, 0 for an anonymous or integration caller.
     *
     * @return int
     */
    private function getCurrentCustomerId(): int
    {
        if ($this->userContext->getUserType() === UserContextInterface::USER_TYPE_CUSTOMER) {
            return (int)$this->userContext->getUserId();
        }

        return 0;
    }
}
