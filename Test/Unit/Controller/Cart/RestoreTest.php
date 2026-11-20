<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Controller\Cart;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Quote\Model\Quote;
use Magmodules\Reloadify\Controller\Cart\Restore;
use Magmodules\Reloadify\Service\Cart\QuoteRestorer;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use Magmodules\Reloadify\Test\Unit\Support\CreatesObjects;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The restore link a Reloadify abandoned cart e-mail points to (reloadify/cart/restore?id=...).
 *
 * The redirect is the storefront's Magento\Framework\Controller\Result\Redirect, not the admin one:
 * typed to the backend class, every path that ended on the homepage was a TypeError and an HTTP 500
 * for the customer. Which quote the visitor gets is QuoteRestorer's decision, see QuoteRestorerTest.
 */
class RestoreTest extends TestCase
{
    use CreatesObjects;
    use CreatesDataModels;

    private RequestInterface&MockObject $request;
    private Redirect&MockObject $redirect;
    private MessageManager&MockObject $messageManager;
    private QuoteRestorer&MockObject $restorer;
    private CustomerSession&MockObject $customerSession;
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
        $this->restorer = $this->createMock(QuoteRestorer::class);
        $this->customerSession = $this->createMock(CustomerSession::class);

        $this->controller = new Restore($context, $this->restorer, $this->customerSession);
    }

    public function testALinkWithoutIdGoesToTheHomepage(): void
    {
        $this->request->method('getParam')->with('id')->willReturn(null);
        $this->restorer->expects(self::never())->method('load');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        self::assertSame($this->redirect, $this->controller->execute());
    }

    public function testAQuoteIsRestoredForTheVisitorAndTheVisitorLandsOnTheCart(): void
    {
        $quote = $this->quote(true);
        $this->request->method('getParam')->willReturn('enc:42');
        $this->restorer->method('load')->with('enc:42')->willReturn($quote);
        $this->customerSession->method('getCustomerId')->willReturn('7');
        $this->restorer->expects(self::once())->method('restore')->with($quote, 7);
        $this->redirect->expects(self::once())->method('setPath')->with('checkout/cart/index');

        $this->controller->execute();
    }

    public function testAGuestVisitorIsCustomerZero(): void
    {
        $quote = $this->quote(true);
        $this->request->method('getParam')->willReturn('enc:42');
        $this->restorer->method('load')->willReturn($quote);
        $this->customerSession->method('getCustomerId')->willReturn(null);
        $this->restorer->expects(self::once())->method('restore')->with($quote, 0);

        $this->controller->execute();
    }

    public function testAQuoteThatBecameAnOrderIsNotRestored(): void
    {
        $this->request->method('getParam')->willReturn('enc:42');
        $this->restorer->method('load')->willReturn($this->quote(false));
        $this->restorer->method('isQuoteConvertedToOrder')->with(42)->willReturn(true);
        $this->restorer->expects(self::never())->method('restore');
        $this->messageManager->expects(self::once())->method('addNoticeMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();
    }

    public function testAnUnknownQuoteGoesToTheHomepageWithANotice(): void
    {
        $this->request->method('getParam')->willReturn('enc:404');
        $this->restorer->method('load')->willThrowException(new NoSuchEntityException());
        $this->messageManager->expects(self::once())->method('addNoticeMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();
    }

    public function testAnyOtherFailureGoesToTheHomepageWithAnError(): void
    {
        $this->request->method('getParam')->willReturn('enc:42');
        $this->restorer->method('load')->willThrowException(new \RuntimeException('Database gone'));
        $this->messageManager->expects(self::once())->method('addErrorMessage');
        $this->redirect->expects(self::once())->method('setPath')->with('cms/index/index');

        $this->controller->execute();
    }

    private function quote(bool $active): Quote
    {
        return $this->createDataModel(Quote::class, ['id' => 42, 'is_active' => $active]);
    }
}
