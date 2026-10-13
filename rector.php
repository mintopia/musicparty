<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use Rector\Renaming\Rector\Name\RenameClassRector;
use RectorLaravel\Rector\Class_\DescriptionPropertyToDescriptionAttributeRector;
use RectorLaravel\Rector\Class_\HiddenPropertyToHiddenAttributeRector;
use RectorLaravel\Rector\Class_\RouteKeyMethodToRouteKeyAttributeRector;
use RectorLaravel\Rector\Class_\SignaturePropertyToSignatureAttributeRector;
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
    ->withPhpSets(php85: true)
    ->withSets([
        LaravelSetList::LARAVEL_130,
    ])
    ->withPreparedSets(deadCode: true)
    ->withSkip([
        ChangeSwitchToMatchRector::class,
        ReadOnlyPropertyRector::class,
        RecastingRemovalRector::class,
        ContainerBindConcreteWithClosureOnlyRector::class,
        // Skipped: keep Laravel's documented property-based configuration rather than attributes.
        DescriptionPropertyToDescriptionAttributeRector::class,
        SignaturePropertyToSignatureAttributeRector::class,
        HiddenPropertyToHiddenAttributeRector::class,
        RouteKeyMethodToRouteKeyAttributeRector::class,
        // Skipped: #[Override] on framework-declared properties is noise, and Laravel's skeleton does not use it.
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        // Skipped: would repoint our VerifyCsrfToken base class to the renamed framework middleware; do that deliberately.
        RenameClassRector::class,
    ]);
