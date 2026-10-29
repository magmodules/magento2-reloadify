<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Catalog\Model\Product\Type;
use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\GroupedProduct\Model\ResourceModel\Product\Link;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order as OrderModel;
use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Order web API service class
 */
class Order
{

    /**
     * Default attribute map output
     */
    public const DEFAULT_MAP = [
        "id" => 'entity_id',
        "currency" => 'order_currency_code',
        "number" => 'increment_id',
        "price" => 'grand_total',
        "status" => 'status',
        "profile_id" => 'customer_id',
        "ordered_at" => 'created_at',
        "created_at" => 'created_at',
        "shopping_cart_id" => 'quote_id'
    ];

    /**
     * @var CollectionFactory
     */
    private $orderCollectionFactory;
    /**
     * @var CustomerRepository
     */
    private $customerRepository;
    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * Mapping from canonical excluded field names to Order-specific keys
     */
    private const EXCLUDED_FIELD_MAP = [
        'telephone' => ['telephone'],
        'street' => ['street'],
        'city' => ['city'],
        'zipcode' => ['postcode'],
        'province' => ['region', 'region_id'],
        'country_code' => ['country_id'],
        'company_name' => ['company'],
        'gender' => ['gender'],
        'birthdate' => ['dob']
    ];

    /**
     * Order constructor.
     * @param CollectionFactory $orderCollectionFactory
     * @param CustomerRepository $customerRepository
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ResourceConnection $resourceConnection
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        CustomerRepository $customerRepository,
        CollectionProcessorInterface $collectionProcessor,
        ResourceConnection $resourceConnection,
        ConfigRepository $configRepository
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->collectionProcessor = $collectionProcessor;
        $this->resourceConnection = $resourceConnection;
        $this->configRepository = $configRepository;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = [], ?SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);
        $excludedFields = $this->configRepository->getExcludedCustomerFields($storeId);
        $excludedOrderKeys = $this->getExcludedOrderKeys($excludedFields);

        /** @var OrderModel $order */
        foreach ($collection as $order) {
            $orderData = [
                "id" => $order->getId(),
                "currency" => $order->getOrderCurrencyCode(),
                "number" => $order->getIncrementId(),
                "price" => $order->getGrandTotal(),
                "paid" => ($order->getTotalPaid() == $order->getGrandTotal()),
                "status" => $order->getStatus(),
                "total_refunded" => (float)$order->getTotalRefunded(),
                "refunded_at" => $this->getRefundedAt($order),
                "coupon_code" => $order->getCouponCode(),
                "discount_amount" => round((float)$order->getDiscountAmount(), 2),
                "has_an_account" => (bool)$order->getCustomerId(),
                "profile" => $this->getProfileData($order, $excludedOrderKeys),
                "products" => $this->getProducts($order),
                "deliver_date" => $this->getDelivery($order),
                "ordered_at" => $order->getCreatedAt(),
                "created_at" => $order->getCreatedAt(),
                "shopping_cart_id" => $order->getQuoteId()
            ];
            if ($shipAddress = $order->getShippingAddress()) {
                $shipData = $shipAddress->getData();
                unset(
                    $shipData['entity_id'],
                    $shipData['parent_id'],
                    $shipData['quote_address_id'],
                    $shipData['customer_address_id']
                );
                $shipData = $this->removeExcludedKeys($shipData, $excludedOrderKeys);
                $orderData['shipping_address'] = array_filter($shipData, function ($value) {
                    return $value !== null;
                });
            }
            if ($billAddress = $order->getBillingAddress()) {
                $billData = $billAddress->getData();
                unset(
                    $billData['entity_id'],
                    $billData['parent_id'],
                    $billData['quote_address_id'],
                    $billData['customer_address_id']
                );
                $billData = $this->removeExcludedKeys($billData, $excludedOrderKeys);
                $orderData['billing_address'] = array_filter($billData, function ($value) {
                    return $value !== null;
                });
            }
            $data[] = $orderData;
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
        ?SearchCriteriaInterface $searchCriteria = null
    ): Collection {
        $collection = $this->orderCollectionFactory->create();
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
     * @param OrderModel $order
     * @param array $excludedKeys
     *
     * @return array|null
     */
    private function getProfileData(OrderModel $order, array $excludedKeys = []): ?array
    {
        try {
            if ($order->getCustomerId()) {
                $customer = $this->customerRepository->getById((int)$order->getCustomerId());
                return [
                    'id' => $order->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            } else {
                $data = [
                    'id' => null,
                    'customer_firstname' => $order->getCustomerFirstname(),
                    'customer_middlename' => $order->getCustomerMiddlename(),
                    'customer_lastname' => $order->getCustomerLastname(),
                    'email' => $order->getCustomerEmail()
                ];
                if ($shipAddress = $order->getShippingAddress()) {
                    $data['region_id'] = $shipAddress->getRegionId();
                    $data['region'] = $shipAddress->getRegion();
                    $data['postcode'] = $shipAddress->getPostcode();
                    $data['street'] = $shipAddress->getStreet();
                    $data['city'] = $shipAddress->getCity();
                    $data['telephone'] = $shipAddress->getTelephone();
                    $data['country_id'] = $shipAddress->getCountryId();
                    $data['company'] = $shipAddress->getCompany();
                }
                $data = $this->removeExcludedKeys($data, $excludedKeys);
                return $data;
            }
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * Get the list of Order-specific keys to exclude based on canonical field names
     *
     * @param array $excludedFields
     * @return array
     */
    private function getExcludedOrderKeys(array $excludedFields): array
    {
        $keys = [[]];
        foreach ($excludedFields as $field) {
            if (isset(self::EXCLUDED_FIELD_MAP[$field])) {
                $keys[] = self::EXCLUDED_FIELD_MAP[$field];
            }
        }
        return array_merge(...$keys);
    }

    /**
     * Remove excluded keys from data array
     *
     * @param array $data
     * @param array $excludedKeys
     * @return array
     */
    private function removeExcludedKeys(array $data, array $excludedKeys): array
    {
        foreach ($excludedKeys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * @param OrderModel $order
     * @return array
     */
    private function getProducts(OrderModel $order): array
    {
        $orderedProducts = [];
        foreach ($order->getAllItems() as $item) {
            //skip variants
            if ($item->getParentItem() && $item->getParentItem()->getProductType() == 'configurable') {
                continue;
            }

            $isBundleChild = $item->getProductType() == Type::TYPE_SIMPLE
                && $item->getParentItem()
                && $item->getParentItem()->getProductType() == Type::TYPE_BUNDLE;

            $orderedProduct = [
                'id' => $item->getProductId(),
                'product_type' => $item->getProductType(),
                'quantity' => $item->getQtyOrdered(),
            ];

            if (!$isBundleChild) {
                $orderedProduct['quantity_refunded'] = (float)$item->getQtyRefunded();
                $orderedProduct['quantity_returned'] = (float)$item->getQtyReturned();
                $orderedProduct['total_refunded'] = (float)$item->getAmountRefunded();
            }

            if ($item->getProductType() == 'configurable') {
                $child = $item->getChildrenItems();
                if (count($child) != 0) {
                    $child = reset($child);
                    $orderedProduct['variant_id'] = $child->getProductId();
                }
            }

            // If it is a simple product associated with a bundle, get the parent bundle product ID
            if ($isBundleChild) {
                $orderedProduct['parent_id'] = $item->getParentItem()->getProductId();
            }

            if ($item->getProductType() == 'grouped') {
                $connection = $this->resourceConnection->getConnection();
                $parentProduct = $connection->select()->from(
                    $this->resourceConnection->getTableName('catalog_product_link'),
                    ['product_id']
                )->where(
                    'link_type_id = ?',
                    Link::LINK_TYPE_GROUPED
                )->where(
                    'linked_product_id = ?',
                    $item->getProductId()
                );
                $orderedProduct['id'] = $connection->fetchOne($parentProduct);
                $orderedProduct['variant_id'] = $item->getProductId();
            }

            $orderedProducts[] = $orderedProduct;
        }

        return $orderedProducts;
    }

    /**
     * @param OrderModel $order
     * @return string
     */
    private function getDelivery(OrderModel $order): string
    {
        $shipment = $order->getShipmentsCollection()->getFirstItem();
        if ($shipment) {
            return (string)$shipment->getCreatedAt();
        }
        return '';
    }

    /**
     * Earliest credit memo created_at, or null when no credit memo exists.
     *
     * @param OrderModel $order
     * @return string|null
     */
    private function getRefundedAt(OrderModel $order): ?string
    {
        $earliest = null;
        foreach ($order->getCreditmemosCollection() as $creditmemo) {
            $createdAt = $creditmemo->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }
            if ($earliest === null || strcmp($createdAt, $earliest) < 0) {
                $earliest = $createdAt;
            }
        }
        return $earliest;
    }

    /**
     * @param Collection $orders
     * @param array $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $orders, array $filters): Collection
    {
        foreach ($filters as $field => $filter) {
            $orders->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }

        return $orders;
    }
}
