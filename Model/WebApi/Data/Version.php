<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\WebApi\Data;

use Magento\Framework\DataObject;
use Magmodules\Reloadify\Api\WebApi\Data\VersionInterface;

/**
 * Version data model
 */
class Version extends DataObject implements VersionInterface
{

    /**
     * @inheritDoc
     */
    public function getExtensionVersion(): string
    {
        return (string)$this->getData(self::EXTENSION_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function setExtensionVersion(string $extensionVersion): VersionInterface
    {
        return $this->setData(self::EXTENSION_VERSION, $extensionVersion);
    }

    /**
     * @inheritDoc
     */
    public function getMagentoVersion(): string
    {
        return (string)$this->getData(self::MAGENTO_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function setMagentoVersion(string $magentoVersion): VersionInterface
    {
        return $this->setData(self::MAGENTO_VERSION, $magentoVersion);
    }
}
