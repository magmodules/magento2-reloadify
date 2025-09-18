<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Error logger handler class
 */
class Error extends Base
{
    protected $loggerType = Logger::ERROR;
    protected $fileName = '/var/log/reloadify-error.log';
}
