<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Service\WebApi;

use Magento\Framework\Exception\IntegrationException;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Integration as IntegrationModel;
use Magento\Integration\Model\Oauth\Token;
use Magmodules\Reloadify\Service\WebApi\Integration;
use Magmodules\Reloadify\Test\Unit\Support\CreatesDataModels;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The access token Reloadify connects with: shown on the config page, created by the data patch and
 * by bin/magento reloadify:integration. Magento's getAccessToken() answers false for a revoked token,
 * which until 1.17.3 was a fatal error on the config page.
 */
class IntegrationTest extends TestCase
{
    use CreatesDataModels;

    private IntegrationServiceInterface&MockObject $integrationService;
    private OauthServiceInterface&MockObject $oauthService;
    private AuthorizationServiceInterface&MockObject $authorizationService;
    private Integration $integration;

    protected function setUp(): void
    {
        $this->integrationService = $this->createMock(IntegrationServiceInterface::class);
        $this->oauthService = $this->createMock(OauthServiceInterface::class);
        $this->authorizationService = $this->createMock(AuthorizationServiceInterface::class);
        $this->integration = new Integration(
            $this->integrationService,
            $this->oauthService,
            $this->authorizationService
        );
    }

    public function testAnExistingTokenIsReturned(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integrationModel(3, 7));
        $this->oauthService->method('getAccessToken')->with(7)->willReturn($this->token('abc'));
        $this->oauthService->expects(self::never())->method('createAccessToken');
        $this->integrationService->expects(self::never())->method('create');

        self::assertSame('abc', $this->integration->createToken());
        self::assertSame('abc', $this->integration->getToken());
    }

    public function testARevokedTokenIsReissued(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integrationModel(3, 7));
        $this->oauthService->method('getAccessToken')->willReturnOnConsecutiveCalls(false, $this->token('new'));
        $this->oauthService->expects(self::once())->method('createAccessToken')->with(7, true);

        self::assertSame('new', $this->integration->createToken());
    }

    public function testATokenThatCannotBeIssuedIsAnError(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integrationModel(3, 7));
        $this->oauthService->method('getAccessToken')->willReturn(false);

        $this->expectException(IntegrationException::class);
        $this->integration->createToken();
    }

    public function testAMissingIntegrationIsCreatedWithTheWebApiResource(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integrationModel(null, null));
        $this->integrationService->expects(self::once())->method('create')
            ->with(self::callback(static fn (array $data): bool => $data['name'] === Integration::INTEGRATION_NAME))
            ->willReturn($this->integrationModel(4, 8));
        $this->authorizationService->expects(self::once())->method('grantPermissions')
            ->with(4, ['Magmodules_Reloadify::webapi']);
        $this->oauthService->expects(self::once())->method('createAccessToken')->with(8, true);
        $this->oauthService->method('getAccessToken')->with(8)->willReturn($this->token('fresh'));

        self::assertSame('fresh', $this->integration->createToken());
    }

    public function testAnUpdateReplacesTheIntegration(): void
    {
        $this->integrationService->method('findByName')->willReturn($this->integrationModel(3, 7));
        $this->integrationService->expects(self::once())->method('delete')->with(3);
        $this->integrationService->expects(self::once())->method('create')->willReturn($this->integrationModel(4, 8));
        $this->oauthService->method('getAccessToken')->with(8)->willReturn($this->token('replaced'));

        self::assertSame('replaced', $this->integration->createToken(true));
    }

    public function testTheConfigPageShowsNothingWithoutAnIntegrationOrToken(): void
    {
        $this->integrationService->method('findByName')
            ->willReturnOnConsecutiveCalls($this->integrationModel(null, null), $this->integrationModel(3, 7));
        $this->oauthService->method('getAccessToken')->willReturn(false);

        self::assertNull($this->integration->getToken(), 'no integration');
        self::assertNull($this->integration->getToken(), 'revoked token');
    }

    private function integrationModel(?int $id, ?int $consumerId): IntegrationModel
    {
        return $this->createDataModel(IntegrationModel::class, ['id' => $id, 'consumer_id' => $consumerId]);
    }

    private function token(string $token): Token
    {
        return $this->createDataModel(Token::class, ['token' => $token]);
    }
}
