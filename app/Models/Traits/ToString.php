<?php

namespace App\Models\Traits;

use ReflectionClass;

trait ToString
{
    public function __toString(): string
    {
        if (method_exists($this, 'toStringModelName')) {
            $className = $this->toStringModelName();
        } else {
            $rClass = new ReflectionClass($this);
            $className = $rClass->getShortName();
        }
        $id = $this->id ?? '#';
        $name = '';
        if (method_exists($this, 'toStringName')) {
            $name = ' ' . $this->toStringName();
        }
        return "[{$className}:{$id}]{$name}";
    }
}
