<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * Order web API service class
 */
class Order
{
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
     * Product constructor.
     * @param CollectionFactory $orderCollectionFactory
     * @param CustomerRepository $customerRepository
     */
    public function __construct(
        CollectionFactory $orderCollectionFactory,
        CustomerRepository $customerRepository
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param int $storeId
     * @param array $extra
     * @return array
     */
    public function execute(int $storeId, array $extra = []): array
    {
        $data = [];
        $orders = $this->orderCollectionFactory->create();
        if ($extra['entity_id']) {
            $orders->addFieldToFilter('entity_id', $extra['entity_id']);
        } else {
            $orders->addFieldToFilter('store_id', $storeId);
            $orders = $this->applyFilter($orders, $extra['filter']);
        }
        /* @var \Magento\Sales\Model\Order $order*/
        foreach ($orders as $order) {
            $profile = null;
            if ($order->getCustomerId()) {
                $customer = $this->customerRepository->getById((int)$order->getCustomerId());
                $profile = [
                    'id' => $order->getCustomerId(),
                    'email' => $customer->getEmail()
                ];
            }
            $data[] = [
                "id" => $order->getId(),
                "currency" => $order->getOrderCurrencyCode(),
                "number" => $order->getIncrementId(),
                "price" => $order->getGrandTotal(),
                "paid" => ($order->getTotalPaid() == $order->getGrandTotal()),
                "status" => $order->getStatus(),
                "profile" => $profile,
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
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    private function getProducts(\Magento\Sales\Model\Order $order)
    {
        $orderedProducts = [];
        foreach ($order->getAllItems() as $item) {
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
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    private function getDelivery(\Magento\Sales\Model\Order $order)
    {
        $shipment = $order->getShipmentsCollection()->getFirstItem();
        if ($shipment) {
            return $shipment->getCreatedAt();
        }
        return '';
    }

    /**
     * @param Collection $orders
     * @param array $filters
     *
     * @return Collection
     */
    private function applyFilter(Collection $orders, array $filters)
    {
        foreach ($filters as $field => $filter) {
            $orders->addFieldToFilter(self::DEFAULT_MAP[$field], $filter);
        }
        return $orders;
    }
}
