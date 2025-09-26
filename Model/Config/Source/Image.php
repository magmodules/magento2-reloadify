<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Image implements OptionSourceInterface
{

    public const BASE = 'image';
    public const SMALL = 'small_image';
    public const THUMBNAIL = 'thumbnail';
    public const SWATCH = 'swatch_image';

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BASE, 'label' => __('Base')],
            ['value' => self::SMALL, 'label' => __('Small')],
            ['value' => self::THUMBNAIL, 'label' => __('Thumbnail')],
            ['value' => self::SWATCH, 'label' => __('Swatch')],
        ];
    }
}
