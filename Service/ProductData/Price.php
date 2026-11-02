<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\ProductData;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\GroupManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;

/**
 * Service class for store view specific price data
 *
 * Magento stores a single price per product, which can include the tax of the default
 * (origin) country. On the storefront this price is converted per store view to the tax
 * rate of that store view's default tax destination. This service performs the same
 * calculation, so the price incl. and excl. tax match what the store view displays.
 */
class Price
{

    /**
     * @var TaxCalculationInterface
     */
    private $taxCalculation;
    /**
     * @var QuoteDetailsInterfaceFactory
     */
    private $quoteDetailsFactory;
    /**
     * @var QuoteDetailsItemInterfaceFactory
     */
    private $quoteDetailsItemFactory;
    /**
     * @var TaxClassKeyInterfaceFactory
     */
    private $taxClassKeyFactory;
    /**
     * @var TaxConfig
     */
    private $taxConfig;
    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var LogRepository
     */
    private $logRepository;
    /**
     * Store ids for which a failed tax calculation has already been logged
     *
     * @var array
     */
    private $loggedStores = [];

    /**
     * Price constructor.
     *
     * @param TaxCalculationInterface          $taxCalculation
     * @param QuoteDetailsInterfaceFactory     $quoteDetailsFactory
     * @param QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory
     * @param TaxClassKeyInterfaceFactory      $taxClassKeyFactory
     * @param TaxConfig                        $taxConfig
     * @param ProductCollectionFactory         $productCollectionFactory
     * @param StoreManagerInterface            $storeManager
     * @param LogRepository                    $logRepository
     */
    public function __construct(
        TaxCalculationInterface $taxCalculation,
        QuoteDetailsInterfaceFactory $quoteDetailsFactory,
        QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory,
        TaxClassKeyInterfaceFactory $taxClassKeyFactory,
        TaxConfig $taxConfig,
        ProductCollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        LogRepository $logRepository
    ) {
        $this->taxCalculation = $taxCalculation;
        $this->quoteDetailsFactory = $quoteDetailsFactory;
        $this->quoteDetailsItemFactory = $quoteDetailsItemFactory;
        $this->taxClassKeyFactory = $taxClassKeyFactory;
        $this->taxConfig = $taxConfig;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->logRepository = $logRepository;
    }

    /**
     * Get the price incl. and excl. tax for a store view
     *
     * Uses the default tax destination of the store view and the tax class of its default
     * customer group, as the storefront does for guests. Falls back to the raw price when the
     * tax calculation fails, so a tax misconfiguration does not break the whole request;
     * the failure is logged once per store view.
     *
     * @param Product    $product
     * @param mixed      $price
     * @param int        $storeId
     *
     * @return array{incl: mixed, excl: mixed}
     */
    public function execute(Product $product, $price, int $storeId): array
    {
        if ($price === null || $price === '' || !(float)$price) {
            return ['incl' => $price, 'excl' => $price];
        }

        try {
            $taxClassKey = $this->taxClassKeyFactory->create()
                ->setType(TaxClassKeyInterface::TYPE_ID)
                ->setValue($product->getTaxClassId());

            $item = $this->quoteDetailsItemFactory->create()
                ->setQuantity(1)
                ->setCode($product->getSku())
                ->setShortDescription($product->getShortDescription())
                ->setTaxClassKey($taxClassKey)
                ->setIsTaxIncluded($this->taxConfig->priceIncludesTax($storeId))
                ->setType('product')
                ->setUnitPrice((float)$price);

            $quoteDetails = $this->quoteDetailsFactory->create()
                ->setItems([$item]);

            $taxDetails = $this->taxCalculation->calculateTax($quoteDetails, $storeId);
            $items = $taxDetails->getItems();
            $taxDetailsItem = array_shift($items);

            return [
                'incl' => $taxDetailsItem->getPriceInclTax(),
                'excl' => $taxDetailsItem->getPrice()
            ];
        } catch (\Throwable $exception) {
            if (!isset($this->loggedStores[$storeId])) {
                $this->loggedStores[$storeId] = true;
                $this->logRepository->addErrorLog(
                    'price tax calculation',
                    sprintf(
                        'Store %s, SKU %s: %s Falling back to stored price.',
                        $storeId,
                        $product->getSku(),
                        rtrim($exception->getMessage(), '.') . '.'
                    )
                );
            }
            return ['incl' => $price, 'excl' => $price];
        }
    }

    /**
     * Get the indexed prices (price, min_price, max_price) in base currency for a store view
     *
     * Read from the price index for the "NOT LOGGED IN" customer group, as the storefront
     * does for guests. Products without an index record are absent from the result.
     *
     * @param array $productIds
     * @param int   $storeId
     *
     * @return array[]
     */
    public function getIndexPrices(array $productIds, int $storeId): array
    {
        if (!$productIds) {
            return [];
        }

        try {
            $collection = $this->productCollectionFactory->create()
                ->setStore($storeId)
                ->addFieldToFilter('entity_id', ['in' => $productIds])
                ->addPriceData(
                    GroupManagement::NOT_LOGGED_IN_ID,
                    $this->storeManager->getStore($storeId)->getWebsiteId()
                );
        } catch (\Throwable $exception) {
            $this->logRepository->addErrorLog(
                'price index',
                sprintf('Store %s: %s', $storeId, $exception->getMessage())
            );
            return [];
        }

        $prices = [];
        foreach ($collection as $product) {
            $prices[$product->getId()] = [
                'price'     => $product->getData('price'),
                'min_price' => $product->getData('min_price'),
                'max_price' => $product->getData('max_price')
            ];
        }

        return $prices;
    }
}
