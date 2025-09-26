<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

class ValidateUrl extends Value
{
    /**
     * @throws ValidatorException
     */
    public function beforeSave(): ValidateUrl
    {
        $value = trim($this->getValue());

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ValidatorException(__('Please enter a valid URL for the PWA Url.'));
        }

        return parent::beforeSave();
    }
}
