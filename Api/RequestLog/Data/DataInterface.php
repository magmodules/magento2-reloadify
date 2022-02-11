<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\RequestLog\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Interface for request log model
 * @api
 */
interface DataInterface extends ExtensibleDataInterface
{

    public const TYPE = 'type';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';

    /**
     * @return string
     */
    public function getEntityId();

    /**
     * @param $entityId
     * @return $this
     */
    public function setEntityId($entityId);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param $type
     * @return $this
     */
    public function setType($type);

    /**
     * @return int
     */
    public function getStoreId();

    /**
     * @param $storeId
     * @return $this
     */
    public function setStoreId($storeId);

    /**
     * @return string
     */
    public function getCreatedAt();
}
