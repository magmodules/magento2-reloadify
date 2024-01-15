<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;
    /**
     * @var null
     */
    private $storeUrl = null;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * Cart constructor.
     *
     * @param CollectionFactory     $quoteCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface    $encryptor
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->collectionProcessor = $collectionProcessor;
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
        $storeUrl = $this->getStoreUrl($storeId);
        if ($storeUrl) {
            return $storeUrl . 'reloadify/cart/restore/?id=' . urlencode($this->encryptor->encrypt($quoteId));
        } else {
            return 'the store does not exists';
        }
    }

    /**
     * Get store url
     *
     * @param int $storeId
     *
     * @return string
     */
    private function getStoreUrl(int $storeId): string
    {
        if ($this->storeUrl === null) {
            try {
                $this->storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
            } catch (NoSuchEntityException $e) {
                $this->storeUrl = '';
            }
        }
        return $this->storeUrl;
    }

    /**
     * @param $quote
     *
     * @return array
     */
    private function getProfileData($quote): array
    {
        if ($quote->getCustomerId()) {
            return [
                'id'    => $quote->getCustomerId(),
                'email' => $quote->getCustomer()->getEmail()
            ];
        } else {
            return [
                'id' => null,
                'email' => $quote->getCustomerEmail()
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
            $quoteProducts[] = $quoteProduct;
        }
        return $quoteProducts;
    }
}
