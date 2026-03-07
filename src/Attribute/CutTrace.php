<?php

declare(strict_types=1);

namespace Testo\Attribute;

use Testo\Lifecycle\Internal\LifecycleAttribute;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a method or function as a stack trace cut point.
 *
 * When an exception is thrown inside a marked method, all deeper internal frames
 * are removed from the trace, so the user sees only relevant code.
 *
 * Use this on custom assertion helpers so that failures point to the test code,
 * not to the internals of the helper:
 *
 * ```
 *  final class ContainerAssert
 *  {
 *      #[CutTrace]
 *      public static function assertContainerHas(Container $container, string $id): void
 *      {
 *          Assert::true($container->has($id), "Service '{$id}' not found in container");
 *      }
 *  }
 * ```
 *
 * Also useful for domain-specific assertion methods in base test classes:
 *
 * ```
 *  trait ApiAssertions
 *  {
 *      #[CutTrace]
 *      protected function assertJsonResponse(Response $response, int $status = 200): void
 *      {
 *          Assert::same($status, $response->getStatusCode());
 *          Assert::string($response->getHeaderLine('Content-Type'))->contains('application/json');
 *      }
 *  }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class CutTrace implements Interceptable, LifecycleAttribute {}
