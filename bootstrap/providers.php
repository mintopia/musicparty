<?php

use App\Mods\Whamageddon\WhammageddonServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;
use SocialiteProviders\Manager\ServiceProvider as SocialiteProvidersServiceProvider;

return [
    WhammageddonServiceProvider::class,
    AppServiceProvider::class,
    AuthServiceProvider::class,
    PrometheusServiceProvider::class,
    HorizonServiceProvider::class,
    SocialiteProvidersServiceProvider::class,
];
