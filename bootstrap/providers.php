<?php

use App\Mods\ModServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;
use App\Providers\TrustedRootUrlServiceProvider;
use SocialiteProviders\Manager\ServiceProvider as SocialiteProvidersServiceProvider;

return [
    AppServiceProvider::class,
    TrustedRootUrlServiceProvider::class,
    ModServiceProvider::class,
    AuthServiceProvider::class,
    PrometheusServiceProvider::class,
    HorizonServiceProvider::class,
    SocialiteProvidersServiceProvider::class,
];
