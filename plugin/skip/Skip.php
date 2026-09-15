<?php

declare(strict_types=1);

namespace Testo;

use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\CaseInterceptable;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Skip\Internal\SkipInterceptor;

/**
 * Marks a test as skipped without deleting or hiding it.
 *
 * The test is not executed, but stays in the results as {@see Status::Skipped}: it is counted in
 * the totals and carries its reason in the result's failure message. Contrast with a group filter
 * (`#[Group('x')]` + `--group=!x`), which drops the test from the results entirely.
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
 * On a class every test of the case is skipped. The attribute is inherited from parent classes,
 * traits and overridden methods; a method-level `#[Skip]` wins over the class-level one, reason
 * included.
 *
 * The failure message reads `{testId} is skipped via #[Skip]`, extended with ` ==> {reason}` when
 * a reason is given. The JUnit, TeamCity and HTML reporters show it; the terminal does not.
 *
 * Runtime contract:
 *
 * - The skipped test never enters the per-test pipeline: `#[BeforeTest]`/`#[AfterTest]`,
 *   data providers, `#[Retry]`/`#[Repeat]`, fibers and coverage never engage. A data-driven
 *   test yields a single Skipped entry.
 * - `#[BeforeClass]`/`#[AfterClass]` still run, also when every test of the case is skipped.
 * - The case class is not constructed for a skipped test.
 * - A run consisting only of skipped tests is successful (exit code 0).
 * - The attribute is inert on a non-test method and on `#[Bench]`/`#[TestInline]` targets.
 *
 * No registration is needed: the attribute wires {@see SkipInterceptor} itself, from a class, a
 * method or a function alike.
 *
 * For skipping at runtime — from the test body, based on the environment — throw {@see SkipTest}
 * instead; the `is skipped via #[Skip]` marker tells the two apart in reports.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
#[FallbackInterceptor(SkipInterceptor::class)]
final readonly class Skip implements CaseInterceptable
{
    /**
     * @param string $reason Why the test is skipped. A reference to an issue
     *        (`'flaky on CI, see ISSUE-123'`) keeps the skip reviewable.
     */
    public function __construct(
        public string $reason = '',
    ) {}
}
