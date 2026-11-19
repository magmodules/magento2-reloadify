<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Model\WebApi;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magmodules\Reloadify\Model\WebApi\CartRestoreRepository;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The PWA counterpart of the restore link: the same rules, answered through the Web API.
 */
class CartRestoreRepositoryTest extends TestCase
{
    use CreatesDataModels;

    private CartRepositoryInterface&MockObject $quoteRepository;
    private CheckoutSession&MockObject $checkoutSession;
    private OrderCollection&MockObject $orders;
    private CartRestoreRepository $repository;

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

        $this->repository = new CartRestoreRepository(
            $encryptor,
            $this->quoteRepository,
            $this->checkoutSession,
            $orderCollectionFactory
        );
    }

    public function testTheDecryptedIdIsLoadedAsAnInteger(): void
    {
        $quote = $this->createDataModel(Quote::class, ['is_active' => true]);
        $this->quoteRepository->expects(self::once())->method('get')->with(self::identicalTo(42))->willReturn($quote);
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($quote);

        self::assertSame($quote, $this->repository->restore('enc:42'));
    }

    public function testAnInactiveQuoteWithoutOrderIsReactivated(): void
    {
        $quote = $this->createDataModel(Quote::class, ['is_active' => false]);
        $this->quoteRepository->method('get')->willReturn($quote);
        $this->orders->method('getSize')->willReturn(0);
        $this->quoteRepository->expects(self::once())->method('save')->with($quote);

        $this->repository->restore('enc:42');

        self::assertTrue((bool)$quote->getIsActive());
    }

    public function testAQuoteThatBecameAnOrderStaysInactive(): void
    {
        $quote = $this->createDataModel(Quote::class, ['is_active' => false]);
        $this->quoteRepository->method('get')->willReturn($quote);
        $this->orders->method('getSize')->willReturn(1);
        $this->quoteRepository->expects(self::never())->method('save');

        $this->repository->restore('enc:42');

        self::assertFalse((bool)$quote->getIsActive());
    }
}
