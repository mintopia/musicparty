<?php

namespace App\Services;

use App\Services\OpenTelemetry\SpanHelper;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\Propagation\TraceContextValidator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

class OpenTelemetry
{
    public static function startSpan(string $name, ?string $serviceName = null, ?string $version = null, ?string $parentTrace = null, ?string $parentState = null): SpanHelper
    {
        if ($name === '') {
            $name = 'span';
        }
        $tracer = Globals::tracerProvider()->getTracer(
            $serviceName ?? config('open-telemetry.service.name'),
            $version ?? config('open-telemetry.service.version'),
            'https://opentelemetry.io/schemas/1.24.0'
        );
        $parent = Span::getCurrent();
        if ($parent instanceof NonRecordingSpan) {
            $builder = $tracer
                ->spanBuilder($name)
                ->setSpanKind(SpanKind::KIND_SERVER);
            $context = null;
            if ($parentTrace !== null) {
                $context = self::getParentContext($parentTrace, $parentState);
                $builder = $builder->setParent($context);
            }
            $span = $builder->startSpan();
            $scope = $span->activate();
        } else {
            $scope = $parent->activate();
            $span = $tracer->spanBuilder($name)->startSpan();
        }

        return new SpanHelper($span, $scope);
    }

    protected static function getParentContext(string $parentTrace, ?string $parentState = null): ?ContextInterface
    {
        $pieces = explode('-', $parentTrace);
        if (count($pieces) < 4) {
            return null;
        }

        [$version, $traceId, $spanId, $traceFlags] = $pieces;
        if (
            !TraceContextValidator::isValidTraceVersion($version)
            || !SpanContextValidator::isValidTraceId($traceId)
            || !SpanContextValidator::isValidSpanId($spanId)
            || !TraceContextValidator::isValidTraceFlag($traceFlags)
        ) {
            return null;
        }

        $convertedTraceFlags = hexdec($traceFlags);
        $isSampled = ($convertedTraceFlags & TraceFlags::SAMPLED) === TraceFlags::SAMPLED;

        $traceState = null;
        if ($parentState !== null) {
            $traceState = new TraceState($parentState);
        }
        $spanContext = SpanContext::createFromRemoteParent(
            $traceId,
            $spanId,
            $isSampled ? TraceFlags::SAMPLED : TraceFlags::DEFAULT,
            $traceState,
        );
        if (!$spanContext->isValid()) {
            return null;
        }
        $context = Context::getCurrent();
        return $context->withContextValue(Span::wrap($spanContext));
    }

    public static function getMeter(?string $serviceName = null, ?string $serviceVersion = null): MeterInterface
    {
        return Globals::meterProvider()->getMeter(
            $serviceName ?? config('open-telemetry.service.name'),
            $serviceVersion ?? config('open-telemetry.service.version'),
            'https://opentelemetry.io/schemas/1.24.0'
        );
    }
}
