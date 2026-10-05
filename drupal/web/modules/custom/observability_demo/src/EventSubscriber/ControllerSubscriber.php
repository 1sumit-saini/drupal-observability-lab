<?php

declare(strict_types=1);

namespace Drupal\observability_demo\EventSubscriber;

use Drupal\observability_demo\OpenTelemetry\TracerFactory;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ControllerSubscriber implements EventSubscriberInterface {

  private ?SpanInterface $span = NULL;
  private ?ScopeInterface $scope = NULL;

  public function __construct(
    private readonly TracerFactory $tracerFactory,
    private readonly LoggerInterface $logger,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::CONTROLLER => ['onController', 1000],
      KernelEvents::RESPONSE => ['onResponse', -900],
    ];
  }

  public function onController(ControllerEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    $route = $request->attributes->get('_route');

    $controller = $request->attributes->get('_controller');

    $this->logger->notice(
        'Observability demo controller event: route=@route controller=@controller',
        [
        '@route' => is_string($route) ? $route : 'unknown',
        '@controller' => is_string($controller) ? $controller : get_debug_type($controller),
        ],
    );

    $this->span = $this->tracerFactory
      ->getTracer()
      ->spanBuilder('Drupal controller')
      ->setAttribute('drupal.route', is_string($route) ? $route : 'unknown')
      ->setAttribute(
        'drupal.controller',
        is_string($controller) ? $controller : 'unknown',
      )
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