<?php

namespace App\Mods\Foo;

use Illuminate\Support\ServiceProvider;

class FooServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('foo-mod-registered', true);
    }
}
