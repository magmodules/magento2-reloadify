<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Model\Config\Source\BaseUrl;

/**
 * Cart web API service class
 */
class Cart
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id"         => 'entity_id',
        "currency"   => 'quote_currency_code',
        "price"      => 'grand_total',
        "profile_id" => 'customer_id',
        "created_at" => 'created_at',
        "updated_at" => 'updated_at'
    ];

    /**
     * @var CollectionFactory
     */
    private $quoteCollectionFactory;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * Cart constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        CollectionProcessorInterface $collectionProcessor,
        ConfigRepository $configRepository
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->encryptor = $encryptor;
        $this->collectionProcessor = $collectionProcessor;
        $this->configRepository = $configRepository;
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

        /* @var Quote $quote */
        foreach ($collection as $quote) {
            $data[] = [
                "id"           => $quote->getId(),
                "currency"     => $quote->getQuoteCurrencyCode(),
                "price"        => $quote->getGrandTotal(),
                "recovery_url" => $this->getRecoveryUrl($storeId, (string)$quote->getId()),
                "profile"      => $this->getProfileData($quote),
                "product_ids"  => $this->getProducts($quote),
                "created_at"   => $quote->getCreatedAt(),
                "updated_at"   => $quote->getUpdatedAt()
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
     */
    private function getCollection(
        int $storeId,
        array $extra = [],
        SearchCriteriaInterface $searchCriteria = null
    ): Collection {
        $collection = $this->quoteCollectionFactory->create();
        if ($extra['entity_id']) {
            $collection->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $collection->addFieldToFilter('store_id', $storeId);
            $collection = $this->applyFilter($collection, $extra['filter']);
        }

        if ($searchCriteria !== null) {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }
        return $collection;
    }

    /**
     * @param Collection $quotes
     * @param array      $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $quotes, array $filters)
    {
        foreach ($filters as $field => $filter) {
            $quotes->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $quotes;
    }

    /**
     * Get recovery url with encrypted quote_id
     *
     * @param int    $storeId
     * @param string $quoteId
     *
     * @return string
     */
    private function getRecoveryUrl(int $storeId, string $quoteId): string
    {
        if ($this->configRepository->getPwaBaseUrl($storeId) == BaseUrl::PWA) {
            $pwaUrl = $this->configRepository->getBaseUrl($storeId);
            return $pwaUrl . '?id=' . urlencode($this->encryptor->encrypt($quoteId));
        }

        $storeUrl = $this->configRepository->getBaseUrlStore($storeId);
        return $storeUrl . 'reloadify/cart/restore/?id=' . urlencode($this->encryptor->encrypt($quoteId));
    }

    /**
     * @param Quote $quote
     *
     * @return array
     */
    private function getProfileData(Quote $quote): array
    {
        if ($quote->getCustomerId()) {
            return [
                'id'    => $quote->getCustomerId(),
                'email' => $quote->getCustomer()->getEmail(),
                'first_name' => $quote->getCustomer()->getFirstname(),
                'middle_name' => $quote->getCustomer()->getMiddlename(),
                'last_name' => $quote->getCustomer()->getLastname()
            ];
        } else {
            return [
                'id' => null,
                'email' => $quote->getCustomerEmail(),
                'first_name' => $quote->getShippingAddress()->getFirstname(),
                'middle_name' => $quote->getShippingAddress()->getMiddlename(),
                'last_name' => $quote->getShippingAddress()->getLastname()
            ];
        }
    }

    /**
     * @param Quote $quote
     *
     * @return array
     */
    private function getProducts(Quote $quote)
    {
        $quoteProducts = [];
        /* @var Item $item */
        foreach ($quote->getAllItems() as $item) {
            //skip variants
            if ($item->getParentItem() && $item->getParentItem()->getProductType() == 'configurable') {
                continue;
            }

            $quoteProduct = [
                'id'       => $item->getProductId(),
                'product_type' => $item->getProductType(),
                'quantity' => $item->getQty()
            ];

            //add variant to parent data
            if ($item->getProductType() == 'configurable') {
                $child = $item->getChildren();
                if (count($child) != 0) {
                    $child = reset($child);
                    $quoteProduct['variant_id'] = $child->getProductId();
                }
            }

            // If it is a simple product associated with a bundle, get the parent bundle product ID
            if ($item->getProductType() == Type::TYPE_SIMPLE &&
                $item->getParentItem() &&
                $item->getParentItem()->getProductType() == Type::TYPE_BUNDLE) {
                $quoteProduct['parent_id'] = $item->getParentItem()->getProductId();
            }

            $quoteProducts[] = $quoteProduct;
        }
        return $quoteProducts;
    }
}
