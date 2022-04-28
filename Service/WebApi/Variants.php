<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Service\ProductData\Stock;

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
     * @var Image
     */
    private $image;
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
     * Variants constructor.
     *
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param Image                    $image
     * @param ResourceConnection       $resourceConnection
     * @param ConfigRepository         $configRepository
     * @param Stock                    $stock
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        Image $image,
        ResourceConnection $resourceConnection,
        ConfigRepository $configRepository,
        Stock $stock,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->image = $image;
        $this->resourceConnection = $resourceConnection;
        $this->configRepository = $configRepository;
        $this->stock = $stock;
        $this->collectionProcessor = $collectionProcessor;
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

        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);
        $stockData = $this->stock->execute($collection->getAllIds());

        foreach ($collection as $product) {
            if (!isset($productIds[$product->getId()])) {
                continue;
            }
            $data[] = [
                "id"           => $product->getId(),
                "title"        => ($name) ? $product->getData($name) : '',
                "article_code" => $product->getSku(),
                "ean"          => ($ean) ? $product->getData($ean) : '',
                "main_image"   => $this->getMainImage($product),
                "price_cost"   => $product->getCost(),
                "price_excl"   => $product->getPrice(),
                "price_incl"   => $product->getPrice(),
                "unit_price"   => $product->getPrice(),
                "sku"          => ($sku) ? $product->getData($sku) : '',
                "brand"        => ($brand) ? $product->getData($brand) : '',
                "stock_level"  => $this->getStockLevel($product, $stockData, $websiteId),
                "product_id"   => $productIds[$product->getId()],
                "created_at"   => $product->getCreatedAt(),
                "updated_at"   => $product->getUpdatedAt()
            ];
        }
        return $data;
    }

    /**
     * @param int|null $entityId
     *
     * @return array
     */
    private function getChildProducts(int $entityId = null)
    {
        $connection = $this->resourceConnection->getConnection();
        $selectProducts = $connection->select()->from(
            $this->resourceConnection->getTableName('catalog_product_super_link'),
            ['product_id', 'parent_id']
        );
        if ($entityId) {
            $selectProducts->where('product_id = ?', $entityId);
        }
        return $connection->fetchPairs($selectProducts);
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
            ->addPriceData()
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
        return $this->image->init($product, 'image')
            ->setImageFile($product->getImage())
            ->getUrl();
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
