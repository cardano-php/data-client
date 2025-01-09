<?php

namespace CardanoPhp\DataClient\Traits;

use ReflectionClass;

trait ToArrayTrait
{
    public function toArray(): array
    {
        $array = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($this);
            if (is_object($value) && method_exists($value, 'toArray')) {
                $array[$property->getName()] = $value->toArray();
            } else {
                $array[$property->getName()] = $value;
            }
        }

        return $array;
    }
}
