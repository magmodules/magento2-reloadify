<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Settings web API service class
 */
class Settings
{

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @param ConfigRepository $configRepository
     */
    public function __construct(
        ConfigRepository $configRepository
    ) {
        $this->configRepository = $configRepository;
    }

    /**
     * @return array
     */
    public function execute(): array
    {
        return [
            [
                "enabled" => $this->configRepository->isEnabled(),
                "extension_version" => $this->configRepository->getExtensionVersion(),
                "magento_version" => $this->configRepository->getMagentoVersion(),
            ]
        ];
    }
}
