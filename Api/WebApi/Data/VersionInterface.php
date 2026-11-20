<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\WebApi\Data;

/**
 * Version data interface
 * @api
 */
interface VersionInterface
{

    public const EXTENSION_VERSION = 'extension_version';
    public const MAGENTO_VERSION = 'magento_version';

    /**
     * Installed version of the Reloadify extension
     *
     * @return string
     */
    public function getExtensionVersion(): string;

    /**
     * @param string $extensionVersion
     *
     * @return $this
     */
    public function setExtensionVersion(string $extensionVersion): VersionInterface;

    /**
     * Version of the Magento installation
     *
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * @param string $magentoVersion
     *
     * @return $this
     */
    public function setMagentoVersion(string $magentoVersion): VersionInterface;
}
