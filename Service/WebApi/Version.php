<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Service\WebApi;

use Magmodules\Reloadify\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Reloadify\Api\WebApi\Data\VersionInterface;
use Magmodules\Reloadify\Api\WebApi\Data\VersionInterfaceFactory;

/**
 * Version web API service class
 */
class Version
{
    /**
     * @param ConfigRepository $configRepository
     * @param VersionInterfaceFactory $versionFactory
     */
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly VersionInterfaceFactory $versionFactory
    ) {
    }

    /**
     * @return VersionInterface
     */
    public function execute(): VersionInterface
    {
        /** @var VersionInterface $version */
        $version = $this->versionFactory->create();

        return $version
            ->setExtensionVersion(ltrim($this->configRepository->getExtensionVersion(), 'v'))
            ->setMagentoVersion($this->configRepository->getMagentoVersion());
    }
}
