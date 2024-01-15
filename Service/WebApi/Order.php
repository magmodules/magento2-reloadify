<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
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
     * Product constructor.
     * @param CollectionFactory $orderCollectionFactory
     * @param CustomerRepository $customerRepository
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        CustomerRepository $customerRepository,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->collectionProcessor = $collectionProcessor;
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
    private function getProfileData($order): ?array
    {
        try {
            if ($order->getCustomerId()) {
                $customer = $this->customerRepository->getById((int)$order->getCustomerId());
                return [
                    'id' => $order->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            } else {
                return [
                    'id' => null,
                    'email' => $order->getCustomerEmail()
                ];
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
                'quantity' => $item->getQtyOrdered(),
            ];
            if ($item->getProductType() == 'configurable') {
                $child = $item->getChildrenItems();
                if (count($child) != 0) {
                    $child = reset($child);
                    $orderedProduct['variant_id'] = $child->getProductId();
                }
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
