<?php

declare(strict_types=1);

namespace Testo\Attribute;

use Testo\Lifecycle\Internal\LifecycleAttribute;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a method or function as an assertion method.
 *
 * When an assertion fails inside a marked method, all deeper internal frames
 * are removed from the stack trace, so the user sees only relevant test code.
 *
 * Use this on custom assertion helpers:
 *
 * ```
 *  final class ContainerAssert
 *  {
 *      #[AssertMethod]
 *      public static function assertContainerHas(Container $container, string $id): void
 *      {
 *          Assert::true($container->has($id), "Service '{$id}' not found in container");
 *      }
 *  }
 * ```
 *
 * Also useful for domain-specific assertions in traits:
 *
 * ```
 *  trait ApiAssertions
 *  {
 *      #[AssertMethod]
 *      protected function assertJsonResponse(Response $response, int $status = 200): void
 *      {
 *          Assert::same($status, $response->getStatusCode());
 *          Assert::string($response->getHeaderLine('Content-Type'))->contains('application/json');
 *      }
 *  }
 * ```
 *
 * Without `#[AssertMethod]`:
 *
 * ```
 *  #0 src/Assert/Internal/StaticState.php(42): Assert::same()
 *  #1 src/Assert.php(35): StaticState::fail()
 *  #2 app/Testing/ApiAssertions.php(12): Assert::same()
 *  #3 tests/Api/OrderTest.php(28): OrderTest->assertJsonResponse()
 * ```
 *
 * With `#[AssertMethod]`:
 *
 * ```
 *  #0 app/Testing/ApiAssertions.php(12): Assert::same()
 *  #1 tests/Api/OrderTest.php(28): OrderTest->assertJsonResponse()
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class AssertMethod implements Interceptable, LifecycleAttribute {}
