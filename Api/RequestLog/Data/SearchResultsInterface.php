<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\RequestLog\Data;

use Magento\Framework\Api\SearchResultsInterface as FrameworkSearchResultsInterface;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterface;

/**
 * Interface for request log search results.
 * @api
 */
interface SearchResultsInterface extends FrameworkSearchResultsInterface
{

    /**
     * Gets request log Items.
     *
     * @return DataInterface[]
     */
    public function getItems();

    /**
     * Sets request log Items.
     *
     * @param DataInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
