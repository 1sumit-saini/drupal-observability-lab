<?php

declare(strict_types=1);

namespace Drupal\observability_demo\OpenTelemetry;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

final class TracerFactory {

  private ?TracerInterface $tracer = NULL;

  public function getTracer(): TracerInterface {
    if ($this->tracer !== NULL) {
      return $this->tracer;
    }

    $transport = (new PsrTransportFactory())->create(
      'http://otel-collector:4318/v1/traces',
      'application/x-protobuf',
    );

    $exporter = new SpanExporter($transport);

    $resource = ResourceInfo::create(
      Attributes::create([
        'service.name' => 'Drupal',
        'service.version' => '11',
        'deployment.environment' => 'development',
      ]),
    );

    $tracerProvider = new TracerProvider(
      spanProcessors: new SimpleSpanProcessor($exporter),
      resource: $resource,
    );

    $this->tracer = $tracerProvider->getTracer(
      'observability_demo',
      '1.0.0',
    );

    return $this->tracer;
  }

}