<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\ProductData;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Service class for stock data
 */
class Stock
{
    public const REQUIRE = [
        'entity_ids'
    ];

    /**
     * @var ResourceConnection
     */
    private $resource;
    /**
     * @var array[]
     */
    private $entityIds;
    /**
     * @var ModuleManager
     */
    private $moduleManager;
    /**
     * @var string
     */
    private $linkField;

    /**
     * Price constructor.
     *
     * @param ResourceConnection $resource
     * @param ModuleManager $moduleManager
     * @param MetadataPool $metadataPool
     * @throws \Exception
     */
    public function __construct(
        ResourceConnection $resource,
        ModuleManager $moduleManager,
        MetadataPool $metadataPool
    ) {
        $this->resource = $resource;
        $this->moduleManager = $moduleManager;
        $this->linkField = $metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }

    /**
     * Get stock data
     *
     * @param array[] $entityIds
     * @return array[]
     */
    public function execute(array $entityIds = []): array
    {
        $this->setData('entity_ids', $entityIds);
        return ($this->isMsiEnabled())
            ? $this->getMsiStock()
            : $this->getNoMsiStock();
    }

    /**
     * @param string $type
     * @param mixed $data
     */
    public function setData($type, $data)
    {
        if (!$data) {
            return;
        }
        switch ($type) {
            case 'entity_ids':
                $this->entityIds = $data;
                break;
        }
    }

    /**
     * Check is MSI enabled
     *
     * @return bool
     */
    private function isMsiEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory');
    }

    /**
     * Get stock qty for specified products if MSI enabled
     *
     * Structure of response
     * [product_id] => [
     *      qty
     *      is_in_stock
     *      reserved
     *      salable_qty
     *      ["msi"]=> [
     *          website_id => [
     *              qty
     *              salable_qty
     *          ]
     *      ]
     * ]
     *
     * @return array[]
     */
    private function getMsiStock(): array
    {
        $result = $this->getNoMsiStock(true);
        $websiteStocks = $this->getChannels();
        if (!$websiteStocks) {
            return $result;
        }
        $stockData = $this->collectMsi(array_unique(array_values($websiteStocks)));
        foreach ($stockData as $productId => $stocks) {
            if (!array_key_exists($productId, $result)) {
                continue;
            }
            foreach ($websiteStocks as $websiteId => $stockId) {
                if (!isset($stocks[$stockId])) {
                    continue;
                }
                $qty = $stocks[$stockId]['qty'];
                $result[$productId]['msi'][$websiteId] = [
                    'qty' => $qty,
                    'salable_qty' => (int)max(0, $qty + $stocks[$stockId]['reserved'])
                ];
            }
        }
        return $result;
    }

    /**
     * Get MSI stock id per website
     *
     * @return array [website_id => stock_id]
     */
    private function getChannels(): array
    {
        $connection = $this->resource->getConnection();
        $selectChannels = $connection->select()
            ->from(
                ['channel' => $this->resource->getTableName('inventory_stock_sales_channel')],
                []
            )->join(
                ['website' => $this->resource->getTableName('store_website')],
                'website.code = channel.code',
                ['website_id']
            )->columns(['stock_id' => 'channel.stock_id'])
            ->where('channel.type = ?', 'website');
        return array_map('intval', $connection->fetchPairs($selectChannels));
    }

    /**
     * Collect MSI stock data from the stock index tables, joined by sku
     *
     * Only the default stock view contains product_id and website_id,
     * custom stock tables only have sku, quantity and is_salable.
     *
     * @param int[] $stockIds
     * @return array [product_id => [stock_id => [qty, reserved]]]
     */
    private function collectMsi(array $stockIds): array
    {
        $connection = $this->resource->getConnection();
        $selectStock = $connection->select()
            ->from(
                ['product' => $this->resource->getTableName('catalog_product_entity')],
                ['entity_id']
            )->where('product.entity_id IN (?)', $this->entityIds);

        $joined = [];
        foreach ($stockIds as $stockId) {
            $stockTable = $this->resource->getTableName(sprintf('inventory_stock_%s', $stockId));
            if (!$connection->isTableExists($stockTable)) {
                continue;
            }
            $alias = sprintf('stock_%s', $stockId);
            $selectStock->joinLeft(
                [$alias => $stockTable],
                "{$alias}.sku = product.sku",
                [sprintf('quantity_%s', $stockId) => 'quantity']
            );
            $reservationAlias = sprintf('reservation_%s', $stockId);
            $selectStock->joinLeft(
                [$reservationAlias => $this->getReservationSelect($stockId)],
                "{$reservationAlias}.sku = product.sku",
                [sprintf('reserved_%s', $stockId) => 'quantity']
            );
            $joined[] = $stockId;
        }
        if (!$joined) {
            return [];
        }

        $result = [];
        foreach ($connection->fetchAll($selectStock) as $row) {
            foreach ($joined as $stockId) {
                if ($row[sprintf('quantity_%s', $stockId)] === null) {
                    continue;
                }
                $result[$row['entity_id']][$stockId] = [
                    'qty' => (int)$row[sprintf('quantity_%s', $stockId)],
                    'reserved' => (int)$row[sprintf('reserved_%s', $stockId)]
                ];
            }
        }
        return $result;
    }

    /**
     * Get summed reservations per sku, optionally limited to one stock
     *
     * @param int|null $stockId
     * @return \Magento\Framework\DB\Select
     */
    private function getReservationSelect(?int $stockId = null)
    {
        $select = $this->resource->getConnection()->select()
            ->from(
                $this->resource->getTableName('inventory_reservation'),
                ['sku', 'quantity' => 'SUM(quantity)']
            )->group('sku');
        if ($stockId !== null) {
            $select->where('stock_id = ?', $stockId);
        }
        return $select;
    }

    /**
     * Get stock qty for products without MSI
     *
     * Structure of response
     * [product_id] => [
     *      qty
     *      is_in_stock
     *      reserved
     *      salable_qty
     *      manage_stock
     *      qty_increments
     *      min_sale_qty
     * ]
     *
     * @param bool $addMsi
     * @return array[]
     */
    private function getNoMsiStock($addMsi = false): array
    {
        $result = [];
        $select = $this->resource->getConnection()
            ->select()
            ->from(
                ['cataloginventory_stock_item' => $this->resource->getTableName('cataloginventory_stock_item')],
                [
                    'product_id',
                    'qty',
                    'is_in_stock',
                    'manage_stock',
                    'qty_increments',
                    'min_sale_qty'
                ]
            )->joinLeft(
                ['catalog_product_entity' => $this->resource->getTableName('catalog_product_entity')],
                "catalog_product_entity.{$this->linkField} = cataloginventory_stock_item.product_id",
                ['sku']
            );
        if ($addMsi) {
            $select->joinLeft(
                ['inventory_reservation' => $this->getReservationSelect()],
                'inventory_reservation.sku = catalog_product_entity.sku',
                ['reserved' => 'COALESCE(inventory_reservation.quantity, 0)']
            );
        }
        $select->where('cataloginventory_stock_item.product_id IN (?)', $this->entityIds);
        $values = $this->resource->getConnection()->fetchAll($select);

        foreach ($values as $value) {
            $result[$value['product_id']] =
                [
                    'qty' => (int)$value['qty'],
                    'is_in_stock' => (int)$value['is_in_stock'],
                    'manage_stock' => (int)$value['manage_stock'],
                    'qty_increments' => (int)$value['qty_increments'],
                    'min_sale_qty' => (int)$value['min_sale_qty']
                ];
            if ($addMsi) {
                $result[$value['product_id']] += [
                    'reserved' => (int)$value['reserved'],
                    'salable_qty' => (int)max($value['qty'], ($value['qty'] - ($value['reserved']) * -1)),
                ];
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function getRequiredParameters(): array
    {
        return self::REQUIRE;
    }

    /**
     * @param string $type
     */
    public function resetData($type = 'all')
    {
        if ($type == 'all') {
            unset($this->entityIds);
        }
        switch ($type) {
            case 'entity_ids':
                unset($this->entityIds);
                break;
        }
    }
}
