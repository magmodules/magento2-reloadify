<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magmodules\Reloadify\Setup\Patch\Data;

use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magmodules\Reloadify\Service\WebApi\Integration as CreateToken;

/**
 * Patch to add token
 */
class Integration implements DataPatchInterface
{

    /**
     * @var Config
     */
    private $configResource;

    /**
     * @var CreateToken
     */
    private $createToken;

    /**
     * Integration constructor.
     * @param Config $configResource
     * @param CreateToken $createToken
     */
    public function __construct(
        Config $configResource,
        CreateToken $createToken
    ) {
        $this->configResource = $configResource;
        $this->createToken = $createToken;
    }

    /**
     * @return array
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return $this
     */
    public function apply()
    {
        $token = $this->createToken->execute();
        $this->configResource->saveConfig('magmodules_reloadify/general/token', $token, 'default', 0);
        return $this;
    }

    /**
     * @return array
     */
    public function getAliases()
    {
        return [];
    }
}
