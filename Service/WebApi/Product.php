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
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Model\RequestLog\CollectionFactory as RequestLogCollectionFactory;
use Magmodules\Reloadify\Model\RequestLog\Collection as RequestLogCollection;

/**
 * Product web API service class
 */
class Product
{

    /**
     *
     */
    public const DEFAULT_MAP = [
        "id" => "entity_id",
        "name" => "name",
        "short_description" => "short_description",
        "price" => "price",
        "relevant_product_ids" => "related_product_ids",
        "category_ids" => "category_ids",
        "created_at" => "created_at",
        "updated_at" => "updated_at"
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
     * Product constructor.
     * @param ProductCollectionFactory $productsCollectionFactory
     * @param Image $image
     * @param CollectionFactory $reviewCollectionFactory
     * @param RequestLogCollectionFactory $requestLogCollectionFactory
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        ProductCollectionFactory $productsCollectionFactory,
        Image $image,
        CollectionFactory $reviewCollectionFactory,
        RequestLogCollectionFactory $requestLogCollectionFactory,
        ConfigRepository $configRepository
    ) {
        $this->productsCollectionFactory = $productsCollectionFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->image = $image;
        $this->requestLogCollection = $requestLogCollectionFactory->create();
        $this->configRepository = $configRepository;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = []): array
    {
        $products = $this->productsCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setStore($storeId);
        if ($extra['entity_id']) {
            $products->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $products = $this->applyFilter($products, $extra['filter'], $storeId);
        }
        $name = $this->configRepository->getName($storeId);
        $data = [];
        /* @var \Magento\Catalog\Model\Product $product*/
        foreach ($products as $product) {
            $data[] = [
                "id" => $product->getId(),
                "name" => ($name) ? $product->getData($name) : '',
                "short_description" => $product->getShortDescription(),
                "price" => $product->getPrice(),
                "url" => $product->getProductUrl(),
                "main_image" => $this->image->init($product, 'image')
                    ->setImageFile($product->getImage())
                    ->getUrl(),
                "visible" => (bool)((int)$product->getVisibility() - 1),
                "variant_ids" => $this->getVariants($product),
                "relevant_product_ids" => $product->getRelatedProductIds(),
                "review_ids" => $this->getReviewIds($product),
                "category_ids" => $product->getCategoryIds(),
                "created_at" => $product->getCreatedAt(),
                "updated_at" => $product->getUpdatedAt()
            ];
        }
        return $data;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getReviewIds(\Magento\Catalog\Model\Product $product)
    {
        return $this->reviewCollectionFactory->create()
            ->addEntityFilter('product', $product->getId())
            ->setDateOrder()
            ->getColumnValues('review_id');
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getVariants(\Magento\Catalog\Model\Product $product)
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
     * @param Collection $products
     * @param array $filters
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
            $products->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $products;
    }
}
