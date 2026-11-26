<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\RequestLog;

use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterface;
use Magmodules\Reloadify\Api\RequestLog\Data\DataInterfaceFactory;

/**
 * Class DataModel
 *
 * Data model for RequestLog
 */
class DataModel extends AbstractModel implements ExtensibleDataInterface, DataInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'reloadify_request_log';

    /**
     * @var DataInterfaceFactory
     */
    protected $dataFactory;

    /**
     * DataModel constructor.
     * @param Context $context
     * @param Registry $registry
     * @param DataObjectHelper $dataObjectHelper
     * @param ResourceModel $resource
     * @param Collection $collection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        protected DataObjectHelper $dataObjectHelper,
        ResourceModel $resource,
        Collection $collection,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $collection, $data);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId()
    {
        return (int)$this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }
}
