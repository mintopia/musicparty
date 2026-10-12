<?php

it('redirects guests from the home page to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
