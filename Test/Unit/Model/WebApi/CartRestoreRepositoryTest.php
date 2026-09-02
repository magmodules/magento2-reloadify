<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Model\WebApi;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magmodules\Reloadify\Model\WebApi\CartRestoreRepository;
use Magmodules\Reloadify\Service\Cart\QuoteRestorer;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The headless counterpart of the restore link: the same rules, answered through the Web API. The
 * caller is the customer behind a customer token, and customer 0 for anyone else.
 */
class CartRestoreRepositoryTest extends TestCase
{
    use CreatesDataModels;

    private QuoteRestorer&MockObject $restorer;
    private UserContextInterface&MockObject $userContext;
    private CartRestoreRepository $repository;

    protected function setUp(): void
    {
        $this->restorer = $this->createMock(QuoteRestorer::class);
        $this->userContext = $this->createMock(UserContextInterface::class);
        $this->repository = new CartRestoreRepository($this->restorer, $this->userContext);
    }

    public function testAnAnonymousCallerRestoresAsCustomerZero(): void
    {
        $quote = $this->quote(true);
        $restored = $this->quote(true);
        $this->restorer->method('load')->with('enc:42')->willReturn($quote);
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_GUEST);
        $this->restorer->expects(self::once())->method('restore')->with($quote, 0)->willReturn($restored);

        self::assertSame($restored, $this->repository->restore('enc:42'));
    }

    public function testACustomerTokenRestoresAsThatCustomer(): void
    {
        $quote = $this->quote(true);
        $this->restorer->method('load')->willReturn($quote);
        $this->userContext->method('getUserType')->willReturn(UserContextInterface::USER_TYPE_CUSTOMER);
        $this->userContext->method('getUserId')->willReturn(7);
        $this->restorer->expects(self::once())->method('restore')->with($quote, 7)->willReturn($quote);

        $this->repository->restore('enc:42');
    }

    public function testAQuoteThatBecameAnOrderIsRefused(): void
    {
        $this->restorer->method('load')->willReturn($this->quote(false));
        $this->restorer->method('isQuoteConvertedToOrder')->willReturn(true);
        $this->restorer->expects(self::never())->method('restore');

        $this->expectException(LocalizedException::class);
        $this->repository->restore('enc:42');
    }

    private function quote(bool $active): Quote
    {
        return $this->createDataModel(Quote::class, ['id' => 42, 'is_active' => $active]);
    }
}
