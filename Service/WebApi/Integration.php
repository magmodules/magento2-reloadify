<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;

/**
 * Service model to create and delete integrations
 */
class Integration
{
    const ENDPOINT_URL = '';
    const INTEGRATION_NAME = 'Reloadify Integration';

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;

    /**
     * @var OauthServiceInterface
     */
    private $oauthService;

    /**
     * @var AuthorizationServiceInterface
     */
    private $authorizationService;

    /**
     * @var LogRepository
     */
    private $logRepository;

    /**
     * Integration constructor.
     * @param IntegrationServiceInterface $integrationService
     * @param OauthServiceInterface $oauthService
     * @param AuthorizationServiceInterface $authorizationService
     * @param LogRepository $logRepository
     */
    public function __construct(
        IntegrationServiceInterface $integrationService,
        OauthServiceInterface $oauthService,
        AuthorizationServiceInterface $authorizationService,
        LogRepository $logRepository
    ) {
        $this->integrationService = $integrationService;
        $this->oauthService = $oauthService;
        $this->authorizationService = $authorizationService;
        $this->logRepository = $logRepository;
    }

    /**
     * Create a new integration
     *
     * @return string
     */
    public function execute(): string
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($integrationId = $integration->getId()) {
            $customerId = $integration->getConsumerId();
            return $this->oauthService->getAccessToken($customerId)->getToken();
        }
        $integrationData = [
            'name' => self::INTEGRATION_NAME,
            'endpoint' => self::ENDPOINT_URL,
            'status' => '1',
            'setup_type' => '0',
        ];
        try {
            $integration = $this->integrationService->create($integrationData);
        } catch (\Exception $exception) {
            $this->logRepository->addErrorLog('Create integration', $exception->getMessage());
            return $exception->getMessage();
        }
        $integrationId = $integration->getId();
        $customerId = $integration->getConsumerId();

        try {
            $this->authorizationService->grantAllPermissions($integrationId);
        } catch (\Exception $exception) {
            $this->logRepository->addErrorLog('Grant integration permissions', $exception->getMessage());
            return $exception->getMessage();
        }
        $this->oauthService->createAccessToken($customerId, true);
        return $this->oauthService->getAccessToken($customerId)->getToken();
    }
}
