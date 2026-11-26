<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Service\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magmodules\Reloadify\Service\Cart\QuoteRestorer;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Restoring an abandoned quote into the visitor's session.
 *
 * The visitor gets the quote itself only when it is theirs: a guest quote for a guest, a customer's
 * quote for that customer. Anyone else opening the link (a guest opening a customer's link, the
 * usual case once the customer is logged out) gets a new quote with the same items, and the
 * original is closed. Magento's guest cart endpoints refuse a quote with a customer, so handing a
 * guest the customer's own quote leaves the cart page empty.
 */
class QuoteRestorerTest extends TestCase
{
    use CreatesDataModels;

    private CartRepositoryInterface&MockObject $quoteRepository;
    private CheckoutSession&MockObject $checkoutSession;
    private OrderCollection&MockObject $orders;
    private QuoteFactory&MockObject $quoteFactory;
    private QuoteIdMaskResource&MockObject $maskResource;
    private AdapterInterface&MockObject $connection;
    private QuoteRestorer $restorer;

    protected function setUp(): void
    {
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $value): string => substr($value, 4));
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->orders = $this->createMock(OrderCollection::class);
        $this->orders->method('addFieldToFilter')->willReturnSelf();
        $orderCollectionFactory = $this->createStub(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturn($this->orders);
        $this->quoteFactory = $this->createMock(QuoteFactory::class);
        $maskFactory = $this->createStub(QuoteIdMaskFactory::class);
        $maskFactory->method('create')->willReturnCallback(fn () => $this->createDataModel(QuoteIdMask::class));
        $this->maskResource = $this->createMock(QuoteIdMaskResource::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $quoteResource = $this->createStub(QuoteResource::class);
        $quoteResource->method('getConnection')->willReturn($this->connection);
        $quoteResource->method('getMainTable')->willReturn('quote');

        $this->restorer = new QuoteRestorer(
            $encryptor,
            $this->quoteRepository,
            $this->checkoutSession,
            $orderCollectionFactory,
            $this->quoteFactory,
            $maskFactory,
            $this->createStub(CustomerRepositoryInterface::class),
            $quoteResource,
            $this->maskResource
        );
    }

    public function testTheEncryptedIdIsLoadedAsAnInteger(): void
    {
        $quote = $this->quote(42, null, true);
        $this->quoteRepository->expects(self::once())->method('get')->with(self::identicalTo(42))->willReturn($quote);

        self::assertSame($quote, $this->restorer->load('enc:42'));
    }

    public function testAGuestGetsTheirOwnGuestQuoteBack(): void
    {
        $quote = $this->quote(42, null, true);
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($quote);
        $this->quoteFactory->expects(self::never())->method('create');

        self::assertSame($quote, $this->restorer->restore($quote, 0));
    }

    public function testACustomerGetsTheirOwnQuoteBack(): void
    {
        $quote = $this->quote(42, 7, true);
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($quote);
        $this->quoteFactory->expects(self::never())->method('create');

        self::assertSame($quote, $this->restorer->restore($quote, 7));
    }

    public function testAnInactiveQuoteWithoutOrderIsReactivated(): void
    {
        $quote = $this->quote(42, null, false);
        $this->orders->method('getSize')->willReturn(0);
        $this->connection->expects(self::once())->method('update')
            ->with('quote', ['is_active' => 1], ['entity_id = ?' => 42]);

        $this->restorer->restore($quote, 0);

        self::assertTrue((bool)$quote->getIsActive());
    }

    public function testAQuoteThatBecameAnOrderIsNotReactivated(): void
    {
        $quote = $this->quote(42, null, false);
        $this->orders->method('getSize')->willReturn(1);
        $this->connection->expects(self::never())->method('update');

        $this->restorer->restore($quote, 0);

        self::assertFalse((bool)$quote->getIsActive());
    }

    public function testAGuestOpeningACustomersLinkGetsANewGuestQuoteAndTheOriginalIsClosed(): void
    {
        $source = $this->quote(42, 7, true);
        $copy = $this->createMock(Quote::class);
        $copy->method('getId')->willReturn(43);
        $copy->expects(self::once())->method('merge')->with($source);
        $copy->expects(self::once())->method('setCustomerIsGuest')->with(true);
        $this->quoteFactory->method('create')->willReturn($copy);
        $this->quoteRepository->expects(self::once())->method('save')->with($copy);
        $this->quoteRepository->method('get')->with(43)->willReturn($copy);
        $this->connection->expects(self::once())->method('update')
            ->with('quote', ['is_active' => 0], ['entity_id = ?' => 42]);
        $this->maskResource->expects(self::once())->method('save')
            ->with(self::callback(static fn (QuoteIdMask $mask): bool => (int)$mask->getData('quote_id') === 43));
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($copy);

        self::assertSame($copy, $this->restorer->restore($source, 0));
        self::assertFalse((bool)$source->getIsActive());
    }

    private function quote(int $id, ?int $customerId, bool $active): Quote
    {
        return $this->createDataModel(Quote::class, [
            'id' => $id,
            'entity_id' => $id,
            'customer_id' => $customerId,
            'is_active' => $active,
            'store_id' => 1,
        ]);
    }
}
