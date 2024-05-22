<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Service\ProductData\Stock;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * Variants web API service class
 */
class Variants
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id"           => 'entity_id',
        "title"        => 'name',
        "article_code" => 'sku',
        "price_cost"   => 'cost',
        "price_excl"   => 'price',
        "price_incl"   => 'price',
        "unit_price"   => 'price',
        "sku"          => 'sku',
        "created_at"   => 'created_at',
        "updated_at"   => 'updated_at'
    ];

    /**
     * @var ProductCollectionFactory
     */
    private $productsCollectionFactory;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var Stock
     */
    private $stock;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    private $mediaPath = '';

    /**
     * Variants constructor.
     *
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param ResourceConnection       $resourceConnection
     * @param ConfigRepository         $configRepository
     * @param Stock                    $stock
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        ResourceConnection $resourceConnection,
        ConfigRepository $configRepository,
        Stock $stock,
        CollectionProcessorInterface $collectionProcessor,
        StoreManagerInterface $storeManager
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->configRepository = $configRepository;
        $this->stock = $stock;
        $this->collectionProcessor = $collectionProcessor;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int   $storeId
     * @param array $extra
     *
     * @return array
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $productIds = $this->getChildProducts($extra['entity_id']);
        $websiteId = $this->configRepository->getStore((int)$storeId)->getWebsiteId();
        $ean = $this->configRepository->getEan($storeId);
        $name = $this->configRepository->getName($storeId);
        $sku = $this->configRepository->getSku($storeId);
        $brand = $this->configRepository->getBrand($storeId);
        $description = $this->configRepository->getDescription($storeId);

        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);
        $stockData = $this->stock->execute($collection->getAllIds());

        foreach ($collection as $product) {
            if (!isset($productIds[$product->getId()])) {
                continue;
            }
            $data[] = [
                "id"           => $product->getId(),
                "title"        => $this->getAttributeValue($product, $name),
                "description"  => $this->getAttributeValue($product, $description),
                "article_code" => $product->getSku(),
                "ean"          => $this->getAttributeValue($product, $ean),
                "main_image"   => $this->getMainImage($product),
                "price_cost"   => $product->getCost(),
                "price_excl"   => $product->getPrice(),
                "price_incl"   => $product->getPrice(),
                "unit_price"   => $product->getPrice(),
                "sku"          => $this->getAttributeValue($product, $sku),
                "brand"        => $this->getAttributeValue($product, $brand),
                "stock_level"  => $this->getStockLevel($product, $stockData, $websiteId),
                "product_id"   => $productIds[$product->getId()],
                "created_at"   => $product->getCreatedAt(),
                "updated_at"   => $product->getUpdatedAt()
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
     * @param int|null $entityId
     *
     * @return array
     */
    private function getChildProducts(int $entityId = null)
    {
        //configurable children
        $connection = $this->resourceConnection->getConnection();
        $selectProducts = $connection->select()->from(
            $this->resourceConnection->getTableName('catalog_product_super_link'),
            ['product_id', 'parent_id']
        );
        if ($entityId) {
            $selectProducts->where('product_id = ?', $entityId);
        }
        $configurable = $connection->fetchPairs($selectProducts);

        //grouped children
        $selectProducts = $connection->select()->from(
            $this->resourceConnection->getTableName('catalog_product_link'),
            ['linked_product_id', 'product_id']
        )->where('link_type_id = 3');
        if ($entityId) {
            $selectProducts->where('linked_product_id = ?', $entityId);
        }
        $grouped = $connection->fetchPairs($selectProducts);

        //bundle children
        $selectProducts = $connection->select()->from(
            ['r' => $this->resourceConnection->getTableName('catalog_product_relation')],
            ['child_id', 'parent_id']
        )->joinLeft(
            ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
            'r.parent_id = e.entity_id',
            []
        )->where(
            'e.type_id = "bundle"'
        );
        if ($entityId) {
            $selectProducts->where('r.child_id = ?', $entityId);
        }
        $bundle = $connection->fetchPairs($selectProducts);

        return $configurable + $grouped + $bundle;
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
        $productIds = $this->getChildProducts($extra['entity_id']);
        $collection = $this->productsCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('entity_id', ['in' => array_keys($productIds)])
            ->setStore($storeId);

        $collection = $this->applyFilter($collection, $extra['filter']);

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
    private function applyFilter(Collection $products, array $filters)
    {
        foreach ($filters as $field => $filter) {
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
        if (!$this->mediaPath) {
            $this->mediaPath = $this->storeManager->getStore($product->getStoreId())
                ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        }
        return $this->mediaPath . 'catalog/product' . $product->getImage();
    }

    /**
     * @param $product
     *
     * @return string
     */
    private function getStockLevel($product, $stockData, $websiteId)
    {
        return isset($stockData[$product->getId()]['msi'][$websiteId])
            ? $stockData[$product->getId()]['msi'][$websiteId]['salable_qty']
            : $stockData[$product->getId()]['qty'];
    }
}
