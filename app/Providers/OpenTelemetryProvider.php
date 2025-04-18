<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OpenTelemetryProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->initOpenTelemetry();
    }

    protected function initOpenTelemetry(): void
    {
        $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => config('open-telemetry.service.namespace'),
            ResourceAttributes::SERVICE_NAME => config('open-telemetry.service.name'),
            ResourceAttributes::SERVICE_VERSION => config('open-telemetry.service.version'),
            ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => config('open-telemetry.environment'),
            ResourceAttributes::HOST_NAME => getenv('HOSTNAME'),
        ])));
        $tracesEndpoint = config('open-telemetry.endpoints.traces');
        $spanExporter = new SpanExporter(
            (new OtlpHttpTransportFactory())->create($tracesEndpoint, ContentTypes::JSON)
        );

        $logsEndpoint = config('open-telemetry.endpoints.logs');
        $logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create($logsEndpoint, ContentTypes::JSON)
        );

        $metricsEndpoint = config('open-telemetry.endpoints.metrics');
        $reader = new ExportingReader(
            new MetricExporter(
                (new OtlpHttpTransportFactory())->create($metricsEndpoint, ContentTypes::JSON)
            )
        );

        $meterProvider = MeterProvider::builder()
            ->setResource($resource)
            ->addReader($reader)
            ->build();

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(
                new SimpleSpanProcessor($spanExporter)
            )
            ->setResource($resource)
            ->setSampler(new ParentBased(new AlwaysOnSampler()))
            ->build();

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor(
                new SimpleLogRecordProcessor($logExporter)
            )
            ->build();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
