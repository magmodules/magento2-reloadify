<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Cart web API service class
 */
class Cart
{

    const DEFAULT_MAP = [
        "id" => 'entity_id',
        "currency" => 'quote_currency_code',
        "price" => 'grand_total',
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

    private $storeUrl = null;

    /**
     * Cart constructor.
     *
     * @param CollectionFactory $quoteCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        CollectionFactory $quoteCollectionFactory,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor
    ) {
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = []): array
    {
        $data = [];
        $quotes = $this->quoteCollectionFactory->create();
        if ($extra['entity_id']) {
            $quotes->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $quotes->addFieldToFilter('store_id', $storeId);
            $quotes = $this->applyFilter($quotes, $extra['filter']);
        }
        /* @var Quote $quote*/
        foreach ($quotes as $quote) {
            $profile = null;
            if ($quote->getCustomerId()) {
                $customer = $quote->getCustomer();
                $profile = [
                    'id' => $quote->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            }
            $data[] = [
                "id" => $quote->getId(),
                "currency" => $quote->getQuoteCurrencyCode(),
                "price" => $quote->getGrandTotal(),
                "recovery_url" => $this->getRecoveryUrl($storeId, (string)$quote->getId()),
                "profile" => $profile,
                "product_ids" => $this->getProducts($quote),
                "created_at" => $quote->getCreatedAt(),
                "updated_at" => $quote->getUpdatedAt()
            ];
        }
        return $data;
    }

    /**
     * @param Quote $quote
     * @return array
     */
    private function getProducts(Quote $quote)
    {
        $quoteProducts = [];
        /* @var Item $item */
        foreach ($quote->getAllItems() as $item) {
            $quoteProducts[] = [
                'id' => $item->getProductId(),
                'quantity' => $item->getQty()
            ];
        }
        return $quoteProducts;
    }

    /**
     * @param Collection $quotes
     * @param array $filters
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
     * @param int $storeId
     * @param int $quoteId
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
}
