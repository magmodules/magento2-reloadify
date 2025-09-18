<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Api\WebApi;

interface CartRestoreInterface
{
    /**
     * Restore a quote using encrypted ID
     *
     * @param string $encryptedId
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function restore(string $encryptedId);
}
