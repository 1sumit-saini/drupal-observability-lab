<?php

declare(strict_types=1);

namespace Drupal\observability_demo\Controller;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\observability_demo\OpenTelemetry\TracerFactory;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EntityDemoController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TracerFactory $tracerFactory,
  ) {}

  public function node(int $node): array {
    $span = $this->tracerFactory
      ->getTracer()
      ->spanBuilder('Drupal entity load')
      ->setAttribute('drupal.entity.type', 'node')
      ->setAttribute('drupal.entity.operation', 'load')
      ->setAttribute('drupal.entity.id', $node)
      ->startSpan();

    try {
      $entity = $this->entityTypeManager
        ->getStorage('node')
        ->load($node);

      if ($entity === NULL) {
        $span->setAttribute('drupal.entity.found', FALSE);
        throw new NotFoundHttpException();
      }

      $span->setAttribute('drupal.entity.found', TRUE);
      $span->setAttribute('drupal.entity.bundle', $entity->bundle());

      return [
        '#type' => 'markup',
        '#markup' => 'Loaded node ' . $entity->id() . ': ' . $entity->label(),
      ];
    }
    catch (\Throwable $e) {
      $span->recordException($e);
      $span->setStatus(StatusCode::STATUS_ERROR);
      throw $e;
    }
    finally {
      $span->end();
    }
  }

}