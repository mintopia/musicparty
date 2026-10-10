<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use Tests\Support\DeployConfig;

function withOtelEnv(array $values, Closure $callback): void
{
    $previous = [];
    foreach ($values as $name => $value) {
        $previous[$name] = getenv($name);
        putenv("$name=$value");
    }

    try {
        $callback();
    } finally {
        foreach ($previous as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
    }
}

it('does not track collector.yml', function (): void {
    expect(trim((string) shell_exec('git ls-files collector.yml '.escapeshellarg(base_path('collector.yml')).' 2>/dev/null')))->toBe('');
    expect(file_exists(base_path('collector.yml')))->toBeFalse();
    expect(file_get_contents(base_path('.gitignore')))->toContain('/collector.yml');
});

it('tracks no Authorization Basic header value', function (): void {
    $files = array_filter(explode("\n", (string) shell_exec('cd '.escapeshellarg(base_path()).' && git ls-files')));
    $offenders = [];

    foreach ($files as $file) {
        $path = base_path($file);
        if (! is_file($path) || str_starts_with($file, 'tests/') || str_starts_with($file, 'openspec/') || str_starts_with($file, 'docs/')) {
            continue;
        }
        if (preg_match('/Authorization["\']?\s*[:=]\s*["\']?Basic\s+[A-Za-z0-9+\/=]{8,}/i', (string) file_get_contents($path)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

it('ships OpenTelemetry off with no exporter endpoint, protocol or headers', function (): void {
    $env = DeployConfig::env('docker/production/production.env');

    expect($env['OTEL_PHP_AUTOLOAD_ENABLED'])->toBe('false')
        ->and(array_keys($env))->not->toContain('OTEL_EXPORTER_OTLP_ENDPOINT', 'OTEL_EXPORTER_OTLP_PROTOCOL', 'OTEL_EXPORTER_OTLP_HEADERS');
});

it('takes the exporter endpoint from OTEL_EXPORTER_OTLP_ENDPOINT', function (): void {
    withOtelEnv(['OTEL_EXPORTER_OTLP_ENDPOINT' => 'http://collector.example:4317'], function (): void {
        expect(Configuration::getString(Variables::OTEL_EXPORTER_OTLP_ENDPOINT))->toBe('http://collector.example:4317');
    });
});
