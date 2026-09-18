<?php

declare(strict_types=1);

namespace Testo;

use Testo\Core\Definition\TestDefinition;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip\SkipPlugin;

/**
 * Marks a test as skipped without deleting or hiding it.
 *
 * The test is not executed but stays in the results as {@see Status::Skipped}, counted in the
 * totals, with the reason in the failure message: `{testId} is skipped via #[Skip]`, extended
 * with ` ==> {reason}` when a reason is given. Contrast with a filter, which drops the test from
 * the run and the results entirely. A run consisting only of skipped tests exits 0.
 *
 * On a class every test of the case is skipped. The attribute is inherited from parent classes,
 * traits and overridden methods; a method-level `#[Skip]` wins over the class-level one, reason
 * included. It is inert on a non-test member and on a case of any type but {@see TestType::Test}.
 *
 * No registration is needed: the attribute wires {@see SkipInterceptor} itself, which reports the
 * test at the entry of its own pipeline, so nothing that prepares, wraps or multiplies a test body
 * engages for it. {@see SkipPlugin}, part of the default suite plugins, sets
 * {@see TestDefinition::$skipped} ahead of the run.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
#[FallbackInterceptor(SkipInterceptor::class)]
final readonly class Skip implements Interceptable
{
    /**
     * @param string $reason Why the test is skipped. A reference to an issue
     *        (`'flaky on CI, see ISSUE-123'`) keeps the skip reviewable.
     */
    public function __construct(
        public string $reason = '',
    ) {}
}
