<?php

declare(strict_types=1);

dataset('compose files', [
    'dev' => ['docker-compose.yaml'],
    'example' => ['example/docker-compose.yml'],
]);

it('starts Reverb on its configured server port without debug', function (string $file) {
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$file);

    expect($contents)
        ->toContain('"reverb:start", "--host=0.0.0.0"')
        ->not->toContain('--debug')
        ->not->toContain('--port');
})->with('compose files');

it('loads the dev Reverb service environment from .env', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/docker-compose.yaml'))
        ->toMatch('/reverb:.*?env_file: \.env\s+entrypoint:/s');
});

it('has no VITE Reverb variables in the env files', function (string $file) {
    expect(file_get_contents(dirname(__DIR__, 2).'/'.$file))->not->toContain('VITE_REVERB');
})->with(['example/.env.example', 'docker/production/production.env', '.env.example']);
