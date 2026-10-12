<?php

it('no longer serves the Spotify cookie relay', function () {
    $this->post('/proxy', ['cookies' => ['sp_dc' => 'x'], 'code' => '1'])
        ->assertNotFound();
});

it('keeps cookies.json untracked', function () {
    expect(shell_exec('git ls-files cookies.json'))->toBeEmpty()
        ->and(file_get_contents(base_path('.gitignore')))->toContain('/cookies.json');
});
