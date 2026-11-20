<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Support;

use Magento\Catalog\Model\Product;

/**
 * A catalog product that holds plain data
 *
 * Product::getSku() asks the type instance, which needs the whole product type pool; everything
 * else the module reads is plain data, so this double answers getSku() and getId() from it too.
 */
class ProductDouble extends Product
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->_data = $data;
        $this->_idFieldName = 'entity_id';
    }

    /**
     * @return string|null
     */
    public function getSku()
    {
        return $this->_data['sku'] ?? null;
    }
}
