<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Support;

use Magento\Framework\DataObject;

/**
 * Builds a Magento data model without its constructor, holding the given data
 *
 * Quote, Product, Integration and Token answer most getters through DataObject::__call() or
 * _getData(), which a PHPUnit 11 mock cannot stub without the deprecated addMethods(). A real
 * instance with its data set behaves exactly as the model does for those getters.
 */
trait CreatesDataModels
{
    /**
     * @template T of DataObject
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    private function createDataModel(string $class, array $data = []): DataObject
    {
        $reflection = new \ReflectionClass($class);
        /** @var T $model */
        $model = $reflection->newInstanceWithoutConstructor();
        if ($reflection->hasProperty('_idFieldName')) {
            $property = $reflection->getProperty('_idFieldName');
            $property->setValue($model, 'id');
        }
        foreach ($data as $key => $value) {
            $model->setData($key, $value);
        }
        return $model;
    }
}
