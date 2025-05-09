<?php

namespace App\Services\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

class SpanHelper
{
    public function __construct(public SpanInterface $span, protected ?ScopeInterface $scope = null)
    {
    }

    public function end(): void
    {
        $this->span->end();
        if ($this->scope !== null) {
            $this->scope->detach();
        }
    }

    /**
     * @param string $name
     * @param array<mixed, mixed> $args
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists($this->span, $name)) {
            $result = $this->span->$name(...$args);
            if ($result instanceof SpanInterface) {
                $this->span = $result;
            }
            return $result;
        }
        return null;
    }
}
