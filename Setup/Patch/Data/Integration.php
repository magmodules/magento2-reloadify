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
     * Integration constructor.
     * @param CreateToken $createToken
     * @param LogRepository $logRepository
     */
    public function __construct(
        private readonly CreateToken $createToken,
        private readonly LogRepository $logRepository
    ) {
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
    public function apply(): self
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
