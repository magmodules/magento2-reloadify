<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Oauth\Token;

/**
 * Service model to create, delete and get integration token
 */
class Integration
{
    public const ENDPOINT_URL = '';
    public const INTEGRATION_NAME = 'Reloadify Integration';

    /**
     * @param IntegrationServiceInterface $integrationService
     * @param OauthServiceInterface $oauthService
     * @param AuthorizationServiceInterface $authorizationService
     */
    public function __construct(
        private readonly IntegrationServiceInterface $integrationService,
        private readonly OauthServiceInterface $oauthService,
        private readonly AuthorizationServiceInterface $authorizationService
    ) {
    }

    /**
     * Create a new integration
     *
     * @param bool $update
     *
     * @return string
     * @throws IntegrationException
     * @throws LocalizedException
     */
    public function createToken(bool $update = false): string
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($integration->getId() && !$update) {
            $customerId = (int)$integration->getConsumerId();
            $token = $this->fetchToken($customerId);
            if ($token !== null) {
                return $token;
            }
            // The integration exists but its token was revoked: issue a new one.
            $this->oauthService->createAccessToken($customerId, true);
            return $this->requireToken($customerId);
        }

        if ($integration->getId() && $update) {
            $this->integrationService->delete($integration->getId());
        }

        $integrationData = [
            'name' => self::INTEGRATION_NAME,
            'endpoint' => self::ENDPOINT_URL,
            'status' => '1',
            'setup_type' => '0',
        ];

        $integration = $this->integrationService->create($integrationData);
        $integrationId = $integration->getId();
        $customerId = (int)$integration->getConsumerId();
        $this->authorizationService->grantPermissions($integrationId, ['Magmodules_Reloadify::webapi']);

        $this->oauthService->createAccessToken($customerId, true);
        return $this->requireToken($customerId);
    }

    /**
     * @return string|null
     */
    public function getToken()
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($integration->getId()) {
            return $this->fetchToken((int)$integration->getConsumerId());
        }
        return null;
    }

    /**
     * @param int $consumerId
     *
     * @return string|null
     */
    private function fetchToken(int $consumerId): ?string
    {
        $token = $this->oauthService->getAccessToken($consumerId);
        return $token instanceof Token ? $token->getToken() : null;
    }

    /**
     * @param int $consumerId
     *
     * @return string
     * @throws IntegrationException
     */
    private function requireToken(int $consumerId): string
    {
        $token = $this->fetchToken($consumerId);
        if ($token === null) {
            throw new IntegrationException(__('Could not create an access token for the Reloadify integration.'));
        }
        return $token;
    }
}
