<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Base Url Option Source model
 */
class BaseUrl implements OptionSourceInterface
{

    public const FRONTEND = 'frontend';
    public const PWA = 'pwa';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FRONTEND, 'label' => __('Frontend')],
            ['value' => self::PWA, 'label' => __('PWA')]
        ];
    }
}
