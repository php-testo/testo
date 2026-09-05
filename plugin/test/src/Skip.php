<?php

declare(strict_types=1);

namespace Testo\Test;

use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Test\Internal\SkipInterceptor;

/**
 * Marks a test as skipped without deleting or hiding it.
 *
 * The test is not executed, but stays in the results as {@see Status::Skipped}: it is counted
 * in the totals and carries its reason in the result's failure message, so parked tests are
 * reviewable instead of silently rotting. Contrast with a group filter (`#[Group('x')]` +
 * `--group=!x`), which drops the test from the results entirely.
 *
 * On a method or function — only that test is skipped:
 *
 * ```
 *  #[Test]
 *  final class OrderTest
 *  {
 *      #[Skip('broken by the pricing rework, see ISSUE-123')]
 *      public function calculatesTotal(): void { ... }  // reported as Skipped, never runs
 *
 *      public function createsOrder(): void { ... }     // runs as usual
 *  }
 * ```
 *
 * On a class — every test of the case is skipped. The attribute is inherited from parent
 * classes and traits (like `#[Group]`); a method-level `#[Skip]` wins over the class-level
 * one, reason included.
 *
 * The failure message reads `{testId} is skipped via #[Skip]`, extended with ` ==> {reason}`
 * when a reason is given. The JUnit, TeamCity and HTML reporters show that message; the
 * terminal prints the skipped line without it, and the compact `--json` report counts the
 * test in its totals.
 *
 * Runtime contract (v1):
 *
 * - The skipped test never enters the per-test pipeline: `#[BeforeTest]`/`#[AfterTest]`
 *   hooks, data providers, `#[Retry]`/`#[Repeat]`, fibers and coverage never engage.
 *   A data-driven test yields a single Skipped entry (providers are not called).
 * - `#[BeforeClass]`/`#[AfterClass]` hooks still run — also when every test of the case
 *   is skipped.
 * - A skipped test never requires an instance of the case class. A fully parked class is
 *   built only when a non-static class-level hook forces it; next to enabled tests the
 *   class is constructed for them as usual.
 * - A run consisting only of `#[Skip]`-marked tests is successful (exit code 0):
 *   Skipped is neither a success nor a failure.
 * - On a non-test method the attribute is inert (like `#[Group]` on a helper). So is it on
 *   a `#[Bench]` or `#[TestInline]` target: only plain test cases are handled.
 *
 * Prerequisite: the handler, {@see SkipInterceptor}, is registered by {@see TestPlugin}.
 * Without the plugin only a class-level `#[Skip]` keeps working — through the
 * {@see FallbackInterceptor} declared below, which the pipeline spawns from class attributes
 * only; a method- or function-level `#[Skip]` is then inert.
 *
 * For skipping at runtime — from the test body, based on the environment — throw
 * {@see SkipTest} instead; the `is skipped via #[Skip]` marker tells the two apart in reports.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
#[FallbackInterceptor(SkipInterceptor::class)]
final readonly class Skip implements Interceptable
{
    /**
     * @param string $reason Why the test is parked. Optional, but a reference to an issue
     *        (`'flaky on CI, see ISSUE-123'`) keeps the skip reviewable.
     */
    public function __construct(
        public string $reason = '',
    ) {}
}
