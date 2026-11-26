<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Service\ProductData;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Module\Manager as ModuleManager;
use Magmodules\Reloadify\Service\ProductData\Stock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Stock per product: the legacy stock item, reservations on top of it when MSI is installed, and
 * per website the stock index of the stock that website sells from (the custom stock fix of 1.17.2).
 *
 * The queries themselves are Magento's business; these tests pin what the service makes of the rows.
 */
class StockTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private ModuleManager&MockObject $moduleManager;
    private Stock $stock;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'join', 'joinLeft', 'columns', 'where', 'group'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $metadata = $this->createStub(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadataPool = $this->createStub(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $this->moduleManager = $this->createMock(ModuleManager::class);
        $this->stock = new Stock($resource, $this->moduleManager, $metadataPool);
    }

    public function testWithoutMsiTheStockItemIsReturnedAsIntegers(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(false);
        $this->connection->method('fetchAll')->willReturn([$this->stockItem(10, '5.0000')]);
        $this->connection->expects(self::never())->method('fetchPairs');

        self::assertSame([
            10 => [
                'qty' => 5,
                'is_in_stock' => 1,
                'manage_stock' => 1,
                'qty_increments' => 1,
                'min_sale_qty' => 1,
            ],
        ], $this->stock->execute([10]));
    }

    public function testOpenOrderReservationsLowerTheSalableQuantity(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->connection->method('fetchPairs')->willReturn([]);
        $this->connection->method('fetchAll')->willReturn([
            $this->stockItem(10, '5.0000', '-2.0000'),
            $this->stockItem(11, '1.0000', '-3.0000'),
            $this->stockItem(12, '4.0000', '0'),
        ]);

        $result = $this->stock->execute([10, 11, 12]);

        self::assertSame(['reserved' => -2, 'salable_qty' => 3], $this->salable($result[10]));
        self::assertSame(['reserved' => -3, 'salable_qty' => 0], $this->salable($result[11]), 'never below zero');
        self::assertSame(['reserved' => 0, 'salable_qty' => 4], $this->salable($result[12]));
        self::assertArrayNotHasKey('msi', $result[10], 'no website is assigned to a stock');
    }

    public function testEachWebsiteGetsTheQuantityOfTheStockItSellsFrom(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        // website 1 sells from the default stock, website 2 from custom stock 2
        $this->connection->method('fetchPairs')->willReturn(['1' => '1', '2' => '2']);
        $this->connection->method('isTableExists')->willReturn(true);
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->stockItem(10, '5.0000', '0'), $this->stockItem(11, '0.0000', '0')],
            [
                ['entity_id' => '10', 'quantity_1' => '5.0000', 'reserved_1' => '-1', 'quantity_2' => '7.0000', 'reserved_2' => null],
                // product 11 is not in custom stock 2's index at all
                ['entity_id' => '11', 'quantity_1' => '0.0000', 'reserved_1' => null, 'quantity_2' => null, 'reserved_2' => null],
                // a product outside the request is ignored
                ['entity_id' => '99', 'quantity_1' => '3.0000', 'reserved_1' => null, 'quantity_2' => null, 'reserved_2' => null],
            ]
        );

        $result = $this->stock->execute([10, 11]);

        self::assertSame([
            1 => ['qty' => 5, 'salable_qty' => 4],
            2 => ['qty' => 7, 'salable_qty' => 7],
        ], $result[10]['msi']);
        self::assertSame([1 => ['qty' => 0, 'salable_qty' => 0]], $result[11]['msi']);
        self::assertArrayNotHasKey(99, $result);
    }

    public function testAStockWithoutAnIndexTableIsLeftOut(): void
    {
        $this->moduleManager->method('isEnabled')->willReturn(true);
        $this->connection->method('fetchPairs')->willReturn(['2' => '2']);
        $this->connection->method('isTableExists')->willReturn(false);
        $this->connection->expects(self::once())->method('fetchAll')
            ->willReturn([$this->stockItem(10, '5.0000', '0')]);

        $result = $this->stock->execute([10]);

        self::assertArrayNotHasKey('msi', $result[10]);
        self::assertSame(5, $result[10]['salable_qty']);
    }

    public function testTheRequiredParametersAreTheEntityIds(): void
    {
        self::assertSame(['entity_ids'], $this->stock->getRequiredParameters());
    }

    /**
     * @return array<string, string|null>
     */
    private function stockItem(int $productId, string $qty, ?string $reserved = null): array
    {
        $row = [
            'product_id' => (string)$productId,
            'qty' => $qty,
            'is_in_stock' => '1',
            'manage_stock' => '1',
            'qty_increments' => '1.0000',
            'min_sale_qty' => '1.0000',
            'sku' => 'sku-' . $productId,
        ];
        if ($reserved !== null) {
            $row['reserved'] = $reserved;
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function salable(array $row): array
    {
        return ['reserved' => $row['reserved'], 'salable_qty' => $row['salable_qty']];
    }
}
