<?php

declare(strict_types=1);

namespace Drupal\observability_demo\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Event\StatementEvent;
use Drupal\Core\Database\Event\StatementExecutionEndEvent;
use Drupal\Core\Database\Event\StatementExecutionFailureEvent;
use Drupal\Core\Database\Event\StatementExecutionStartEvent;
use Drupal\observability_demo\OpenTelemetry\TracerFactory;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DatabaseSubscriber implements EventSubscriberInterface {

  /**
   * Active database spans keyed by statement object ID.
   *
   * @var array<int, SpanInterface>
   */
  private array $spans = [];

  public function __construct(
    private readonly TracerFactory $tracerFactory,
    private readonly Connection $database,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 2000],
      StatementExecutionStartEvent::class => 'onStatementStart',
      StatementExecutionEndEvent::class => 'onStatementEnd',
      StatementExecutionFailureEvent::class => 'onStatementFailure',
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $this->database->enableEvents(StatementEvent::all());
  }

  public function onStatementStart(StatementExecutionStartEvent $event): void {
    $span = $this->tracerFactory
      ->getTracer()
      ->spanBuilder('Drupal database query')
      ->setAttribute('db.system', 'mysql')
      ->setAttribute('drupal.database.key', $event->key)
      ->setAttribute('drupal.database.target', $event->target)
      ->setAttribute('drupal.database.query', $event->queryString)
      ->startSpan();

    $this->spans[$event->statementObjectId] = $span;
  }

  public function onStatementEnd(StatementExecutionEndEvent $event): void {
    if (!isset($this->spans[$event->statementObjectId])) {
      return;
    }

    $span = $this->spans[$event->statementObjectId];

    $span->setAttribute(
      'drupal.database.duration_ms',
      $event->getElapsedTime() * 1000,
    );

    $span->end();

    unset($this->spans[$event->statementObjectId]);
  }

  public function onStatementFailure(StatementExecutionFailureEvent $event): void {
    if (!isset($this->spans[$event->statementObjectId])) {
      return;
    }

    $span = $this->spans[$event->statementObjectId];

    $span->setAttribute(
      'drupal.database.duration_ms',
      $event->getElapsedTime() * 1000,
    );

    $span->setAttribute(
      'drupal.database.exception.class',
      $event->exceptionClass,
    );

    $span->setAttribute(
      'drupal.database.exception.code',
      (string) $event->exceptionCode,
    );

    $span->recordException(
      new \RuntimeException(
        $event->exceptionMessage,
        is_int($event->exceptionCode) ? $event->exceptionCode : 0,
      ),
    );

    $span->setStatus(StatusCode::STATUS_ERROR);
    $span->end();

    unset($this->spans[$event->statementObjectId]);
  }

}