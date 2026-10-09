<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Class_\DescriptionPropertyToDescriptionAttributeRector;
use RectorLaravel\Rector\Class_\HiddenPropertyToHiddenAttributeRector;
use RectorLaravel\Rector\Class_\RouteKeyMethodToRouteKeyAttributeRector;
use RectorLaravel\Rector\Class_\SignaturePropertyToSignatureAttributeRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
        __DIR__.'/routes',
    ])
    ->withPhpSets(php84: true)
    ->withSets([LaravelSetList::LARAVEL_130])
    ->withPreparedSets(deadCode: true)
    ->withSkip([
        __DIR__.'/app/Console',
        DescriptionPropertyToDescriptionAttributeRector::class,
        SignaturePropertyToSignatureAttributeRector::class,
        HiddenPropertyToHiddenAttributeRector::class,
        RouteKeyMethodToRouteKeyAttributeRector::class,
    ]);
