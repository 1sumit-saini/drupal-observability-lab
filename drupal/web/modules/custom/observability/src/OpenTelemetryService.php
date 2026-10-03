<?php

declare(strict_types=1);

namespace Drupal\observability;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;

final class OpenTelemetryService {

  public function __construct() {
    $transport = (new OtlpHttpTransportFactory())->create(
      'http://otel-collector:4318/v1/traces',
      'application/x-protobuf'
    );

    $exporter = new SpanExporter($transport);

    $resource = ResourceInfo::create(
      Attributes::create([
        'service.name' => 'observability-drupal',
        'service.version' => '1.0.0',
        'deployment.environment' => 'local',
      ])
    );

    $tracerProvider = (new TracerProviderBuilder())
      ->addSpanProcessor(new SimpleSpanProcessor($exporter))
      ->setResource($resource)
      ->build();

    Globals::registerInitializer(
      static function (Configurator $configurator) use ($tracerProvider): Configurator {
        return $configurator->withTracerProvider($tracerProvider);
      }
    );
  }

}