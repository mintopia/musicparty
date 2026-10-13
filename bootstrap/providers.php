<?php

use App\Mods\ModServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;
use SocialiteProviders\Manager\ServiceProvider as SocialiteProvidersServiceProvider;

return [
    AppServiceProvider::class,
    ModServiceProvider::class,
    AuthServiceProvider::class,
    PrometheusServiceProvider::class,
    HorizonServiceProvider::class,
    SocialiteProvidersServiceProvider::class,
];
