<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\UrlInterface;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Model\RequestLog\Collection as RequestLogCollection;
use Magmodules\Reloadify\Model\RequestLog\CollectionFactory as RequestLogCollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var AttributeRepositoryInterface
     */
    private $attributeRepository;

    private $mediaPath = '';

    /**
     * Product constructor.
     *
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param CollectionFactory $reviewCollectionFactory
     * @param RequestLogCollectionFactory $requestLogCollectionFactory
     * @param ConfigRepository $configRepository
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Visibility $productVisibility
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        CollectionFactory $reviewCollectionFactory,
        RequestLogCollectionFactory $requestLogCollectionFactory,
        ConfigRepository $configRepository,
        CollectionProcessorInterface $collectionProcessor,
        Visibility $productVisibility,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->requestLogCollection = $requestLogCollectionFactory->create();
        $this->configRepository = $configRepository;
        $this->collectionProcessor = $collectionProcessor;
        $this->productVisibility = $productVisibility;
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
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
        $eanType = $this->getAttributeType($ean);

        $name = $this->configRepository->getName($storeId);
        $nameType = $this->getAttributeType($name);

        $sku = $this->configRepository->getSku($storeId);
        $skuType = $this->getAttributeType($sku);

        $brand = $this->configRepository->getBrand($storeId);
        $brandType = $this->getAttributeType($brand);

        $description = $this->configRepository->getDescription($storeId);
        $descriptionType = $this->getAttributeType($description);

        $extraFields = $this->configRepository->getExtraFields();
        foreach ($extraFields as $key => $extraAttribute) {
            $extraAttributeType = $this->getAttributeType($extraAttribute['source']);
            $extraFields[$key]['type'] = $extraAttributeType;
        }

        foreach ($collection as $product) {
            $productData = [
                "id"                   => $product->getId(),
                "name"                 => $this->getAttributeValue($product, $name, $nameType),
                'product_type'         => $product->getTypeId(),
                "ean"                  => $this->getAttributeValue($product, $ean, $eanType),
                "short_description"    => $product->getShortDescription(),
                "description"          => $this->getAttributeValue($product, $description, $descriptionType),
                "price"                => $product->getPrice(),
                "url"                  => $product->getProductUrl(),
                "sku"                  => $this->getAttributeValue($product, $sku, $skuType),
                "brand"                => $this->getAttributeValue($product, $brand, $brandType),
                "main_image"           => $this->getMainImage($product),
                "visible"              => (bool)((int)$product->getVisibility() - 1),
                "variant_ids"          => $this->getVariants($product),
                "relevant_product_ids" => $product->getRelatedProductIds(),
                "review_ids"           => $this->getReviewIds($product),
                "category_ids"         => $product->getCategoryIds(),
                "created_at"           => $product->getCreatedAt(),
                "updated_at"           => $product->getUpdatedAt()
            ];
            foreach ($extraFields as $extraField) {
                $productData[$extraField['label']] = $this->getAttributeValue(
                    $product,
                    $extraField['source'],
                    $extraField['type']
                );
            }
            $data[] = $productData;
        }

        return $data;
    }

    /**
     * @param $product
     * @param $attribute
     * @return mixed|string
     */
    private function getAttributeValue($product, $attribute, $type)
    {
        $value = '';
        if ($attribute && $type) {
            if (($type == 'select') || ($type == 'multiselect')) {
                $value = $product->getAttributeText($attribute);
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
     * Get the main image URL for a product.
     *
     * @param \Magento\Catalog\Model\Product $product The product instance.
     * @return string The full URL of the product's main image, or an empty string if no image exists.
     */
    private function getMainImage($product): string
    {
        if ($image = $product->getImage()) {
            return $this->getMediaBaseUrl($product->getStoreId())
                . 'catalog/product'
                . $this->normalizeImagePath($image);
        }

        return '';
    }

    /**
     * Retrieve the base URL for media files for a specific store.
     *
     * @param int $storeId The store ID.
     * @return string The base media URL for the store.
     */
    private function getMediaBaseUrl(int $storeId): string
    {
        if (!$this->mediaPath) {
            $this->mediaPath = $this->storeManager->getStore($storeId)
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        }

        return $this->mediaPath;
    }

    /**
     * Normalize the image path by ensuring it starts with a forward slash.
     *
     * @param string $image The image path from the product.
     * @return string The normalized image path.
     */
    private function normalizeImagePath(string $image): string
    {
        return '/' . ltrim($image, '/');
    }

    /**
     * Returns simple products array for parent product
     *
     * @param ProductModel $product
     *
     * @return array
     */
    private function getVariants(ProductModel $product)
    {
        $ids = [];
        $childProducts = null;
        switch ($product->getTypeId()) {
            case 'configurable':
                $childProducts = $product->getTypeInstance()->getUsedProducts($product);
                break;
            case 'grouped':
                $childProducts = $product->getTypeInstance()->getAssociatedProducts($product);
                break;
        }

        if ($childProducts) {
            foreach ($childProducts as $childProduct) {
                if ($childProduct->getTypeId() == 'simple') {
                    $ids[] = $childProduct->getId();
                }
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

    /**
     * @param $attribute
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getAttributeType($attribute): string
    {
        if ($attribute == 'entity_id') {
            return 'text';
        }
        return $this->attributeRepository->get(ProductAttributeInterface::ENTITY_TYPE_CODE, $attribute)
            ->getFrontendInput();
    }
}
