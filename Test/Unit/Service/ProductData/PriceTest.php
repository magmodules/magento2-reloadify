<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Service\ProductData;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Reloadify\Service\ProductData\Price;
use Magmodules\Reloadify\Test\Unit\Support\ProductDouble;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Store view prices incl. and excl. tax (1.17.2): the calculation, and the fallback to the stored
 * price that keeps a tax misconfiguration from breaking the products and variants endpoints.
 */
class PriceTest extends TestCase
{
    private TaxCalculationInterface&MockObject $taxCalculation;
    private ProductCollectionFactory&MockObject $collectionFactory;
    private LogRepository&MockObject $logRepository;
    private Price $price;

    protected function setUp(): void
    {
        $this->taxCalculation = $this->createMock(TaxCalculationInterface::class);
        $this->collectionFactory = $this->createMock(ProductCollectionFactory::class);
        $this->logRepository = $this->createMock(LogRepository::class);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->price = new Price(
            $this->taxCalculation,
            $this->factory(QuoteDetailsInterfaceFactory::class, $this->fluent(QuoteDetailsInterface::class)),
            $this->factory(QuoteDetailsItemInterfaceFactory::class, $this->fluent(QuoteDetailsItemInterface::class)),
            $this->factory(TaxClassKeyInterfaceFactory::class, $this->fluent(TaxClassKeyInterface::class)),
            $this->createStub(TaxConfig::class),
            $this->collectionFactory,
            $storeManager,
            $this->logRepository
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function pricesWithoutTaxCalculation(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero' => [0],
            'zero string' => ['0.0000'],
        ];
    }

    #[DataProvider('pricesWithoutTaxCalculation')]
    public function testAnEmptyPriceIsReturnedAsIsWithoutCalculatingTax(mixed $price): void
    {
        $this->taxCalculation->expects(self::never())->method('calculateTax');

        self::assertSame(['incl' => $price, 'excl' => $price], $this->price->execute($this->product(), $price, 1));
    }

    public function testThePricesComeFromTheTaxCalculation(): void
    {
        $this->taxCalculation->method('calculateTax')->willReturn($this->taxDetails([$this->taxItem(121.0, 100.0)]));

        self::assertSame(['incl' => 121.0, 'excl' => 100.0], $this->price->execute($this->product(), '121.0000', 1));
    }

    public function testAFailingCalculationFallsBackToTheStoredPriceAndLogsOncePerStore(): void
    {
        $this->taxCalculation->method('calculateTax')->willThrowException(new \RuntimeException('No tax rate.'));
        $logged = [];
        $this->logRepository->method('addErrorLog')->willReturnCallback(
            function (string $type, $message) use (&$logged): void {
                $logged[] = $message;
            }
        );

        self::assertSame(['incl' => 10, 'excl' => 10], $this->price->execute($this->product(), 10, 1));
        self::assertSame(['incl' => 20, 'excl' => 20], $this->price->execute($this->product(), 20, 1));
        self::assertSame(['incl' => 30, 'excl' => 30], $this->price->execute($this->product(), 30, 2));

        self::assertSame([
            'Store 1, SKU sku-1: No tax rate. Falling back to stored price.',
            'Store 2, SKU sku-1: No tax rate. Falling back to stored price.',
        ], $logged);
    }

    /**
     * @return array<string, array{array<int, TaxDetailsItemInterface>|null}>
     */
    public static function emptyTaxDetails(): array
    {
        return ['no items' => [[]], 'null items' => [null]];
    }

    #[DataProvider('emptyTaxDetails')]
    public function testACalculationWithoutItemsFallsBackToTheStoredPrice(?array $items): void
    {
        $this->taxCalculation->method('calculateTax')->willReturn($this->taxDetails($items));
        $this->logRepository->expects(self::once())->method('addErrorLog')->with(
            'price tax calculation',
            'Store 1, SKU sku-1: The tax calculation returned no item. Falling back to stored price.'
        );

        self::assertSame(['incl' => 15, 'excl' => 15], $this->price->execute($this->product(), 15, 1));
    }

    public function testIndexPricesForNoProductsSkipTheQuery(): void
    {
        $this->collectionFactory->expects(self::never())->method('create');

        self::assertSame([], $this->price->getIndexPrices([], 1));
    }

    public function testIndexPricesAreKeyedByProductId(): void
    {
        $collection = $this->productCollection([
            new ProductDouble([
                'entity_id' => 5, 'price' => '10.0000', 'min_price' => '8.0000', 'max_price' => '12.0000',
            ]),
        ]);
        $collection->expects(self::once())->method('addPriceData')->with(0, 1);
        $this->collectionFactory->method('create')->willReturn($collection);

        self::assertSame(
            [5 => ['price' => '10.0000', 'min_price' => '8.0000', 'max_price' => '12.0000']],
            $this->price->getIndexPrices([5], 1)
        );
    }

    public function testAFailingIndexQueryReturnsNothingAndLogs(): void
    {
        $collection = $this->productCollection([]);
        $collection->method('addPriceData')->willThrowException(new \RuntimeException('Index missing'));
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->logRepository->expects(self::once())->method('addErrorLog')
            ->with('price index', 'Store 1: Index missing');

        self::assertSame([], $this->price->getIndexPrices([5], 1));
    }

    private function product(): Product
    {
        return new ProductDouble(['sku' => 'sku-1', 'tax_class_id' => 2]);
    }

    private function taxItem(float $incl, float $excl): TaxDetailsItemInterface
    {
        $item = $this->createStub(TaxDetailsItemInterface::class);
        $item->method('getPriceInclTax')->willReturn($incl);
        $item->method('getPrice')->willReturn($excl);
        return $item;
    }

    /**
     * @param array<int, TaxDetailsItemInterface>|null $items
     */
    private function taxDetails(?array $items): TaxDetailsInterface
    {
        $details = $this->createStub(TaxDetailsInterface::class);
        $details->method('getItems')->willReturn($items);
        return $details;
    }

    /**
     * @param array<int, Product> $items
     */
    private function productCollection(array $items): ProductCollection&MockObject
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('setStore')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        return $collection;
    }

    /**
     * A stub whose setters return itself, as the Magento data interfaces do
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function fluent(string $class): object
    {
        $mock = $this->createMock($class);
        foreach (get_class_methods($class) as $method) {
            if (str_starts_with($method, 'set')) {
                $mock->method($method)->willReturnSelf();
            }
        }
        return $mock;
    }

    /**
     * @param class-string $factoryClass
     */
    private function factory(string $factoryClass, object $product): MockObject
    {
        $factory = $this->createMock($factoryClass);
        $factory->method('create')->willReturn($product);
        return $factory;
    }
}
