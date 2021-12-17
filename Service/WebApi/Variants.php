<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Variants web API service class
 */
class Variants
{
    const DEFAULT_MAP = [
        "id" => 'entity_id',
        "title" => 'name',
        "article_code" => 'sku',
        "price_cost" => 'cost',
        "price_excl" => 'price',
        "price_incl" => 'price',
        "unit_price" => 'price',
        "sku" => 'sku',
        "created_at" => 'created_at',
        "updated_at" => 'updated_at'
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
     * @var GetSalableQuantityDataBySku
     */
    private $getSalableQuantityDataBySku;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * Variants constructor.
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param Image $image
     * @param ResourceConnection $resourceConnection
     * @param GetSalableQuantityDataBySku $getSalableQuantityDataBySku
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        Image $image,
        ResourceConnection $resourceConnection,
        GetSalableQuantityDataBySku $getSalableQuantityDataBySku,
        ConfigRepository $configRepository
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->image = $image;
        $this->resourceConnection = $resourceConnection;
        $this->getSalableQuantityDataBySku = $getSalableQuantityDataBySku;
        $this->configRepository = $configRepository;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = []): array
    {
        $productIds = $this->getChildProducts($extra['entity_id']);
        try {
            $products = $this->productsCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('entity_id', ['in' => array_keys($productIds)])
                ->addPriceData()
                ->setStore($storeId);
        } catch (\Exception $exception) {
            return [];
        }
        $data = [];
        $products = $this->applyFilter($products, $extra['filter']);
        $ean = $this->configRepository->getEan($storeId);
        $name = $this->configRepository->getName($storeId);
        $sku = $this->configRepository->getSku($storeId);
        /* @var \Magento\Catalog\Model\Product $product*/
        foreach ($products as $product) {
            if (!isset($productIds[$product->getId()])) {
                continue;
            }
            $data[] = [
                "id" => $product->getId(),
                "title" => ($name) ? $product->getData($name) : '',
                "article_code" => $product->getSku(),
                "ean" => ($ean) ? $product->getData($ean) : '',
                "main_image" => $this->image->init($product, 'image')
                    ->setImageFile($product->getImage())
                    ->getUrl(),
                "price_cost" => $product->getCost(),
                "price_excl" => $product->getPrice(),
                "price_incl" => $product->getPrice(),
                "unit_price" => $product->getPrice(),
                "sku" => ($sku) ? $product->getData($sku) : '',
                "stock_level" => $this->getSalableQuantityDataBySku->execute($product->getSku())[0]['qty'],
                "product_id" => $productIds[$product->getId()],
                "created_at" => $product->getCreatedAt(),
                "updated_at" => $product->getUpdatedAt()
            ];
        }
        return $data;
    }

    /**
     * @param int|null $entityId
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
     * @param Collection $products
     * @param array $filters
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
}
