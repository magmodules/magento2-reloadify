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
 * Debug logger handler class
 */
class Debug extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/reloadify-debug.log';
}
