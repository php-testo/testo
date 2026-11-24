<?php

declare(strict_types=1);

namespace Testo;

use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip\SkipPlugin;

/**
 * Runs a test only when its requirement is met; otherwise reports it as {@see Status::Skipped},
 * the way {@see Skip} does, with the failure message `{testId} is skipped: requirement
 * {condition}() is not met`, extended with ` ==> {reason}` when a reason is given.
 *
 * ```
 *  #[Requires([self::class, 'hasDocker'], 'needs a Docker daemon')]
 *  public function startsContainer(): void { ... }
 *
 *  private static function hasDocker(): bool { ... }
 * ```
 *
 * Repeatable; every requirement has to be met. Requirements accumulate across the class, its
 * parents and traits, and the test itself with the methods it overrides. A {@see Skip} on the test
 * takes precedence: its test is skipped without any requirement being checked.
 *
 * Requirements are checked when the tests are located: a method-level one once per test, a
 * class-level one once for the whole case. An unmet one keeps the test as far from running as
 * {@see Skip} does: lifecycle hooks, data providers and retries never engage. It is inert on a
 * non-test member and on a case of any type but {@see TestType::Test}.
 *
 * No registration is needed: the attribute wires {@see SkipInterceptor} itself. {@see SkipPlugin},
 * part of the default suite plugins, sets {@see TestDefinition::$skipped} ahead of the run.
 *
 * @api
 */
#[\Attribute(
    \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE,
)]
#[FallbackInterceptor(SkipInterceptor::class)]
final readonly class Requires implements Interceptable
{
    /**
     * @param (\Closure(): bool)|non-empty-string|array{non-empty-string, non-empty-string} $condition
     *        Met when it returns `true`. Takes no arguments. Attribute arguments allow a static
     *        method as `[Class::class, 'method']` or `'Class::method'` (any visibility, so a
     *        private helper fits; `self` and `static` stand for the test class), a bare name — a
     *        static method of the test class when it has one, as with `#[DataProvider]`, a
     *        function otherwise — and since PHP 8.5 a `static function` closure (not an arrow
     *        function) or a first-class callable such as `hasDocker(...)`. A condition that throws
     *        or returns anything but a bool reports the test as {@see Status::Aborted}.
     * @param string $reason Why the test needs it, appended to the skip message.
     */
    public function __construct(
        public \Closure|string|array $condition,
        public string $reason = '',
    ) {}
}
