<?php

namespace App\Services\OpenTelemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;

class Logger
{
    /**
     * @param array<string, mixed> $config
     * @return \Monolog\Logger
     */
    public function __invoke(array $config): \Monolog\Logger
    {
        $handler = new Handler(
            Globals::loggerProvider(),
            $config['level'] ?? 'debug',
        );
        return new \Monolog\Logger($config['name'] ?? 'opentelemetry', [$handler]);
    }
}
