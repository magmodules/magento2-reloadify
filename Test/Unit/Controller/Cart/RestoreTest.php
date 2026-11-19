<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magmodules\Reloadify\Controller\Cart\Restore;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use Magmodules\Reloadify\Test\Unit\Support\CreatesObjects;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The restore link a Reloadify abandoned cart e-mail points to (reloadify/cart/restore?id=...).
 *
 * The redirect is the storefront's Magento\Framework\Controller\Result\Redirect, not the admin
 * one: until 1.17.3 the controller was typed to the backend class, so every path that ended on the
 * homepage was a TypeError and an HTTP 500 for the customer.
 */
class RestoreTest extends TestCase
{
    use CreatesObjects;
    use CreatesDataModels;

    private RequestInterface&MockObject $request;
    private Redirect&MockObject $redirect;
    private MessageManager&MockObject $messageManager;
    private CartRepositoryInterface&MockObject $quoteRepository;
    private CheckoutSession&MockObject $checkoutSession;
    private OrderCollection&MockObject $orders;
    private Restore $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_REDIRECT)->willReturn($this->redirect);
        $this->messageManager = $this->createMock(MessageManager::class);

        $context = $this->createObject(Context::class, [
            'request' => $this->request,
            'resultFactory' => $resultFactory,
            'messageManager' => $this->messageManager,
        ]);

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $value): string => substr($value, 4));
        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->orders = $this->createMock(OrderCollection::class);
        $this->orders->method('addFieldToFilter')->willReturnSelf();
        $orderCollectionFactory = $this->createStub(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturn($this->orders);

        $this->controller = new Restore(
            $context,
            $encryptor,
            $this->quoteRepository,
            $this->checkoutSession,
            $orderCollectionFactory
        );
    }

    public function testALinkWithoutIdGoesToTheHomepage(): void
    {
        $this->request->method('getParam')->with('id')->willReturn(null);
        $this->quoteRepository->expects(self::never())->method('get');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        self::assertSame($this->redirect, $this->controller->execute());
    }

    public function testAnActiveQuoteIsPutBackInTheSession(): void
    {
        $quote = $this->quote(42, true);
        $this->request->method('getParam')->willReturn('enc:42');
        $this->quoteRepository->method('get')->with(42)->willReturn($quote);
        $this->quoteRepository->expects(self::never())->method('save');
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($quote);
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart/index');

        $this->controller->execute();
    }

    public function testAnInactiveQuoteWithoutOrderIsReactivated(): void
    {
        $quote = $this->quote(42, false);
        $this->request->method('getParam')->willReturn('enc:42');
        $this->quoteRepository->method('get')->willReturn($quote);
        $this->orders->method('getSize')->willReturn(0);
        $this->quoteRepository->expects(self::once())->method('save')->with($quote);
        $this->checkoutSession->expects(self::once())->method('replaceQuote')->with($quote);
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart/index');

        $this->controller->execute();

        self::assertTrue((bool)$quote->getIsActive());
    }

    public function testAQuoteThatBecameAnOrderIsNotRestored(): void
    {
        $quote = $this->quote(42, false);
        $this->request->method('getParam')->willReturn('enc:42');
        $this->quoteRepository->method('get')->willReturn($quote);
        $this->orders->method('getSize')->willReturn(1);
        $this->quoteRepository->expects(self::never())->method('save');
        $this->checkoutSession->expects(self::never())->method('replaceQuote');
        $this->messageManager->expects(self::once())->method('addNoticeMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();

        self::assertFalse((bool)$quote->getIsActive());
    }

    public function testAnUnknownQuoteGoesToTheHomepageWithANotice(): void
    {
        $this->request->method('getParam')->willReturn('enc:404');
        $this->quoteRepository->method('get')->willThrowException(new NoSuchEntityException());
        $this->messageManager->expects(self::once())->method('addNoticeMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();
    }

    public function testAnyOtherFailureGoesToTheHomepageWithAnError(): void
    {
        $this->request->method('getParam')->willReturn('enc:42');
        $this->quoteRepository->method('get')->willThrowException(new \RuntimeException('Database gone'));
        $this->messageManager->expects(self::once())->method('addErrorMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();
    }

    private function quote(int $id, bool $active): Quote
    {
        return $this->createDataModel(Quote::class, ['id' => $id, 'is_active' => $active]);
    }
}
