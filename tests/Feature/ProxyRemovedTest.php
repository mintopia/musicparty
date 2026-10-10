<?php

it('has no proxy endpoint', function () {
    $this->post('/proxy', ['cookies' => ['sp_dc' => 'x'], 'code' => '1'])->assertNotFound();
});

it('keeps the cookie file out of git', function () {
    expect(base_path('cookies.json'))->not->toBeFile();
});
