<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Model\RequestLog\Collection as RequestLogCollection;
use Magmodules\Reloadify\Model\RequestLog\CollectionFactory as RequestLogCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;

/**
 * Product web API service class
 */
class Product
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id"                   => "entity_id",
        "name"                 => "name",
        "short_description"    => "short_description",
        "price"                => "price",
        "relevant_product_ids" => "related_product_ids",
        "category_ids"         => "category_ids",
        "created_at"           => "created_at",
        "updated_at"           => "updated_at"
    ];

    /**
     * @var ProductCollectionFactory
     */
    private $productsCollectionFactory;
    /**
     * @var Image
     */
    private $image;
    /**
     * @var CollectionFactory
     */
    private $reviewCollectionFactory;
    /**
     * @var RequestLogCollection
     */
    private $requestLogCollection;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;
    /**
     * @var Visibility
     */
    private $productVisibility;

    /**
     * Product constructor.
     *
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param Image $image
     * @param CollectionFactory $reviewCollectionFactory
     * @param RequestLogCollectionFactory $requestLogCollectionFactory
     * @param ConfigRepository $configRepository
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Visibility $productVisibility
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        Image $image,
        CollectionFactory $reviewCollectionFactory,
        RequestLogCollectionFactory $requestLogCollectionFactory,
        ConfigRepository $configRepository,
        CollectionProcessorInterface $collectionProcessor,
        Visibility $productVisibility
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->image = $image;
        $this->requestLogCollection = $requestLogCollectionFactory->create();
        $this->configRepository = $configRepository;
        $this->collectionProcessor = $collectionProcessor;
        $this->productVisibility = $productVisibility;
    }

    /**
     * @param int                          $storeId
     * @param array                        $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return array
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);
        $ean = $this->configRepository->getEan($storeId);
        $name = $this->configRepository->getName($storeId);
        $sku = $this->configRepository->getSku($storeId);
        $brand = $this->configRepository->getBrand($storeId);

        foreach ($collection as $product) {
            $data[] = [
                "id"                   => $product->getId(),
                "name"                 => $this->getAttributeValue($product, $name),
                "ean"                  => $this->getAttributeValue($product, $ean),
                "short_description"    => $product->getShortDescription(),
                "price"                => $product->getPrice(),
                "url"                  => $product->getProductUrl(),
                "sku"                  => $this->getAttributeValue($product, $sku),
                "brand"                => $this->getAttributeValue($product, $brand),
                "main_image"           => $this->getMainImage($product),
                "visible"              => (bool)((int)$product->getVisibility() - 1),
                "variant_ids"          => $this->getVariants($product),
                "relevant_product_ids" => $product->getRelatedProductIds(),
                "review_ids"           => $this->getReviewIds($product),
                "category_ids"         => $product->getCategoryIds(),
                "created_at"           => $product->getCreatedAt(),
                "updated_at"           => $product->getUpdatedAt()
            ];
        }

        return $data;
    }

    /**
     * @param $product
     * @param $attribute
     * @return mixed|string
     */
    private function getAttributeValue($product, $attribute)
    {
        $value = '';
        if ($attribute) {
            if ($dropdownValue = $product->getAttributeText($attribute)) {
                $value = $dropdownValue;
            } else {
                $value = $product->getData($attribute);
            }
        }
        return $value;
    }

    /**
     * @param int                          $storeId
     * @param array                        $extra
     * @param SearchCriteriaInterface|null $searchCriteria
     *
     * @return Collection
     */
    private function getCollection(
        int $storeId,
        array $extra = [],
        SearchCriteriaInterface $searchCriteria = null
    ): Collection {
        $collection = $this->productsCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setStore($storeId)
            ->setVisibility($this->productVisibility->getVisibleInSiteIds());
        if ($extra['entity_id']) {
            $collection->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $collection = $this->applyFilter($collection, $extra['filter'], $storeId);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        return $collection;
    }

    /**
     * @param Collection $products
     * @param array      $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $products, array $filters, int $storeId = null)
    {
        if (in_array('delta', $filters) && $this->requestLogCollection->getSize()) {
            $lastRequestDate = $this->requestLogCollection->addFieldToFilter(
                'type',
                ['in' => ['products', 'products-delta']]
            )->addFieldToFilter('store_id', $storeId)
                ->setOrder('entity_id', 'DESC')
                ->setPageSize(1)
                ->setCurPage(1)
                ->getFirstItem()
                ->getCreatedAt();
            $products->addFieldToFilter('updated_at', ['gt' => $lastRequestDate]);
            return $products;
        }
        foreach ($filters as $field => $filter) {
            if ($filter == 'delta') {
                continue;
            }
            $products->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $products;
    }

    /**
     * @param $product
     *
     * @return string
     */
    private function getMainImage($product)
    {
        return $this->image->init($product, 'image')
            ->setImageFile($product->getImage())
            ->getUrl();
    }

    /**
     * Returns simple products array for configurable product type
     *
     * @param ProductModel $product
     *
     * @return array
     */
    private function getVariants(ProductModel $product)
    {
        $ids = [];
        if ($product->getTypeId() == 'configurable') {
            $products = $product->getTypeInstance()->getUsedProducts($product);
            $ids = [];
            foreach ($products as $product) {
                $ids[] = $product->getId();
            }
        }
        return $ids;
    }

    /**
     * Retruns review collection by product
     *
     * @param ProductModel $product
     *
     * @return array
     */
    private function getReviewIds(ProductModel $product)
    {
        return $this->reviewCollectionFactory->create()
            ->addEntityFilter('product', $product->getId())
            ->setDateOrder()
            ->getColumnValues('review_id');
    }
}
