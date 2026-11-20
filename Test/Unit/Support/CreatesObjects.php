<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Support;

/**
 * Builds a class through its constructor, filling every argument not given with a stub or its default
 *
 * Replaces Magento\Framework\TestFramework\Unit\Helper\ObjectManager, which calls the TestCase's
 * protected getMockBuilder() from outside and breaks under PHPUnit 11. The stubs come from the TestCase
 * itself, so PHPUnit generates return values for their typed methods (a Context stub hands out an event
 * manager stub, and so on).
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait CreatesObjects
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $arguments Constructor arguments by parameter name
     * @return T
     */
    private function createObject(string $class, array $arguments = []): object
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        $values = [];
        foreach ($constructor ? $constructor->getParameters() : [] as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $arguments)) {
                $values[] = $arguments[$name];
                continue;
            }
            // Object arguments get a stub even when optional, as Magento's helper did: AbstractModel::_init()
            // asks the (optional) resource model for its id field name straight away
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $values[] = $this->isContext($type->getName())
                    ? $this->createObject($type->getName())
                    : $this->createStub($type->getName());
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $values[] = $parameter->getDefaultValue();
                continue;
            }
            $values[] = match ($type instanceof \ReflectionNamedType ? $type->getName() : '') {
                'array' => [],
                'string' => '',
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                default => null,
            };
        }
        return new $class(...$values);
    }

    /**
     * Magento's *\Context classes only hold services, and their getters declare no return type, so a stub of
     * one hands out null (getEventDispatcher()->dispatch() on null). Build them for real instead, from stubs.
     *
     * @param string $class
     * @return bool
     */
    private function isContext(string $class): bool
    {
        return str_ends_with($class, '\\Context') && class_exists($class)
            && !(new \ReflectionClass($class))->isAbstract();
    }
}
