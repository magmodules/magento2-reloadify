<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection;

/**
 * Category web API service class
 */
class Category
{

    const DEFAULT_MAP = [
        "id" =>'entity_id',
        "name" => 'name',
        "created_at" => 'created_at',
        "updated_at" => 'updated_at'
    ];

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * Category constructor.
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = []): array
    {
        try {
            $categories = $this->collectionFactory->create()
                ->addAttributeToSelect('*')
                ->setStore($storeId);
            if ($extra['entity_id']) {
                $categories->addFieldToFilter('entity_id', $extra['entity_id']);
            } else {
                $categories = $this->applyFilter($categories, $extra['filter']);
            }
        } catch (\Exception $exception) {
            return [];
        }

        $data = [];
        /* @var \Magento\Catalog\Model\Category $category*/
        foreach ($categories as $category) {
            $dataNew = [
                "id" => $category->getId(),
                "name" => $category->getName(),
                "url" => $category->getUrl(),
                "visible" => $category->getIsActive(),
                "product_ids" => $category->getProductCollection()->getColumnValues('entity_id'),
                "created_at" => $category->getCreatedAt(),
                "updated_at" => $category->getUpdatedAt()
            ];
            try {
                $dataNew['parent_category_id'] = $category->getParentCategory()->getId();
            } catch (\Exception $e) {
                $data[] = $dataNew;
                continue;
            }
            $data[] = $dataNew;
        }
        return $data;
    }

    /**
     * @param Collection $categories
     * @param array $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $categories, array $filters)
    {
        foreach ($filters as $field => $filter) {
            $categories->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $categories;
    }
}
