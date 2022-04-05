<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Category web API service class
 */
class Category
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
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
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * Category constructor.
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @param int                          $storeId
     * @param array                        $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);

        foreach ($collection as $category) {
            $data[] = [
                "id" => $category->getId(),
                "name" => $category->getName(),
                "url" => $category->getUrl(),
                "visible" => $category->getIsActive(),
                "product_ids" => $category->getProductCollection()->getColumnValues('entity_id'),
                "parent_category_id" => $this->getParentCategoryId($category),
                "created_at" => $category->getCreatedAt(),
                "updated_at" => $category->getUpdatedAt()
            ];
        }
        return $data;
    }

    /**
     * @param int                          $storeId
     * @param array                        $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return Collection
     * @throws LocalizedException
     */
    private function getCollection(
        int $storeId,
        array $extra = [],
        SearchCriteriaInterface $searchCriteria = null
    ): Collection {
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->setStore($storeId);
        if ($extra['entity_id']) {
            $collection->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $collection = $this->applyFilter($collection, $extra['filter']);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        return $collection;
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

    /**
     * @param $category
     *
     * @return int|null
     */
    private function getParentCategoryId($category): ?int
    {
        try {
            return (int)$category->getParentCategory()->getId();
        } catch (\Exception $e) {
            return null;
        }
    }
}
