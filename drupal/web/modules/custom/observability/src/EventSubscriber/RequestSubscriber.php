<?php

declare(strict_types=1);

namespace Drupal\observability\EventSubscriber;

use Drupal\observability\OpenTelemetryService;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestSubscriber implements EventSubscriberInterface {

  private mixed $span = NULL;

  private mixed $scope = NULL;

  public function __construct(OpenTelemetryService $openTelemetry) {

  }

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 1000],
      KernelEvents::RESPONSE => ['onResponse', -1000],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    $tracer = Globals::tracerProvider()
      ->getTracer('drupal.observability');

    $this->span = $tracer
      ->spanBuilder($request->getMethod() . ' ' . $request->getPathInfo())
      ->setSpanKind(SpanKind::KIND_SERVER)
      ->startSpan();

    $this->span->setAttribute(
      'http.request.method',
      $request->getMethod()
    );

    $this->span->setAttribute(
      'url.path',
      $request->getPathInfo()
    );

    $this->scope = $this->span->activate();
  }

  public function onResponse(ResponseEvent $event): void {
    if ($this->span === NULL) {
      return;
    }

    $this->span->setAttribute(
      'http.response.status_code',
      $event->getResponse()->getStatusCode()
    );

    $this->scope?->detach();
    $this->span->end();

    $this->scope = NULL;
    $this->span = NULL;
  }

}