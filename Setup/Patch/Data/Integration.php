<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magmodules\Reloadify\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Reloadify\Service\WebApi\Integration as CreateToken;

/**
 * Patch to add token
 */
class Integration implements DataPatchInterface
{

    /**
     * @var CreateToken
     */
    private $createToken;

    /**
     * @var LogRepository
     */
    private $logRepository;

    /**
     * Integration constructor.
     * @param CreateToken $createToken
     * @param LogRepository $logRepository
     */
    public function __construct(
        CreateToken $createToken,
        LogRepository $logRepository
    ) {
        $this->createToken = $createToken;
        $this->logRepository = $logRepository;
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
        try {
            $this->createToken->createToken();
        } catch (\Exception $exception) {
            $this->logRepository->addErrorLog('Integration patch', $exception->getMessage());
        }

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
