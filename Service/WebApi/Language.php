<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Store\Model\ResourceModel\Store\Collection;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;

/**
 * Languages web API service class
 */
class Language
{

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param int|null $entityId
     *
     * @return array
     */
    public function execute(int $entityId = null): array
    {
        $data = [];
        $collection = $this->getCollection($entityId);

        foreach ($collection as $store) {
            $data[] = [
                "id"         => $store->getId(),
                "active"     => $store->isActive(),
                "code"       => $store->getCode(),
                "name"       => $store->getName(),
                "url"        => $store->getBaseUrl(),
                "website_id" => $store->getWebsiteId()
            ];
        }
        return $data;
    }

    /**
     * @param $entityId
     *
     * @return Collection
     */
    private function getCollection($entityId): Collection
    {
        $collection = $this->collectionFactory->create();
        if ($entityId) {
            $collection->addFieldToFilter('store_id', $entityId);
        }

        return $collection;
    }
}
