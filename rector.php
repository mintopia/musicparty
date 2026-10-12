<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use RectorLaravel\Rector\MethodCall\ContainerBindConcreteWithClosureOnlyRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php82: true)
    ->withSets([
        LaravelSetList::LARAVEL_120,
    ])
    ->withPreparedSets(deadCode: true)
    ->withSkip([
        ChangeSwitchToMatchRector::class,
        ReadOnlyPropertyRector::class,
        RecastingRemovalRector::class,
        ContainerBindConcreteWithClosureOnlyRector::class,
    ]);
