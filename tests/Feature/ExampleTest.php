<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the anonymous home page for guests', function () {
    $this->withoutVite()->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome', false));
});
