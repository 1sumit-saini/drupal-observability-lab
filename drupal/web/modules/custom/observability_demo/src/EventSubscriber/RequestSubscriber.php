<?php

namespace Drupal\observability_demo\EventSubscriber;

use Drupal\observability_demo\OpenTelemetry\TracerFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;

final class RequestSubscriber implements EventSubscriberInterface {

  private ?SpanInterface $span = NULL;
  private ?ScopeInterface $scope = NULL;

  public function __construct(
    private readonly TracerFactory $tracerFactory,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 3000],
      KernelEvents::RESPONSE => ['onResponse', -1000],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    $this->span = $this->tracerFactory
      ->getTracer()
      ->spanBuilder('Drupal request')
      ->setAttribute('http.request.method', $request->getMethod())
      ->setAttribute('url.path', $request->getPathInfo())
      ->startSpan();
    
    $this->scope = $this->span->activate();
  }

  public function onResponse(ResponseEvent $event): void {
    if (!$event->isMainRequest() || $this->span === NULL) {
      return;
    }

    $this->span
      ->setAttribute(
        'http.response.status_code',
        $event->getResponse()->getStatusCode(),
      )
      ->end();

    if ($this->scope !== NULL) {
        $this->scope->detach();
        $this->scope = NULL;
    }

    $this->span->end();
    $this->span = NULL;
  }

}