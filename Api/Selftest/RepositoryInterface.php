<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\Selftest;

/**
 * Self test repository interface
 */
interface RepositoryInterface
{

    /**
     * Test everything
     *
     * @param bool $output
     * @return array
     */
    public function test($output = true): array;
}
