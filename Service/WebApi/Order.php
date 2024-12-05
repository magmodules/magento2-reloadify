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
     * Order constructor.
     * @param CollectionFactory $orderCollectionFactory
     * @param CustomerRepository $customerRepository
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        CustomerRepository $customerRepository,
        CollectionProcessorInterface $collectionProcessor,
        ResourceConnection $resourceConnection
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->collectionProcessor = $collectionProcessor;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = [], SearchCriteriaInterface $searchCriteria = null): array
    {
        $data = [];
        $collection = $this->getCollection($storeId, $extra, $searchCriteria);

        foreach ($collection as $order) {
            $data[] = [
                "id" => $order->getId(),
                "currency" => $order->getOrderCurrencyCode(),
                "number" => $order->getIncrementId(),
                "price" => $order->getGrandTotal(),
                "paid" => ($order->getTotalPaid() == $order->getGrandTotal()),
                "status" => $order->getStatus(),
                "profile" => $this->getProfileData($order),
                "products" => $this->getProducts($order),
                "deliver_date" => $this->getDelivery($order),
                "ordered_at" => $order->getCreatedAt(),
                "created_at" => $order->getCreatedAt(),
                "shopping_cart_id" => $order->getQuoteId()
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
     * @param $order
     *
     * @return array|null
     */
    private function getProfileData(OrderModel $order): ?array
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
                return $data;
            }
        } catch (\Exception $exception) {
            return null;
        }
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

            $orderedProduct = [
                'id' => $item->getProductId(),
                'product_type' => $item->getProductType(),
                'quantity' => $item->getQtyOrdered(),
            ];

            if ($item->getProductType() == 'configurable') {
                $child = $item->getChildrenItems();
                if (count($child) != 0) {
                    $child = reset($child);
                    $orderedProduct['variant_id'] = $child->getProductId();
                }
            }

            // If it is a simple product associated with a bundle, get the parent bundle product ID
            if ($item->getProductType() == Type::TYPE_SIMPLE &&
                $item->getParentItem() &&
                $item->getParentItem()->getProductType() == Type::TYPE_BUNDLE) {
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
