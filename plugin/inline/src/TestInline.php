<?php

declare(strict_types=1);

namespace Testo\Inline;

use Testo\Assert;
use Testo\Inline\Internal\InlineInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Test that a method or function returns a specified result when called with given arguments.
 *
 * This attribute enables inline testing - declaring test cases directly on the method
 * using attributes instead of writing separate test methods. Useful for testing pure
 * functions with table-driven tests.
 *
 * Examples:
 *
 * Basic usage with expected value
 * ```
 *  #[TestInline(arguments: [1, 1], result: 2)]
 *  #[TestInline(arguments: [40, 2], result: 42)]
 *  public function sum(int $a, int $b): int
 *  {
 *      return $a + $b;
 *  }
 * ```
 *
 * Testing void methods
 *
 * ```
 *  #[TestInline(arguments: [])]
 *  public function initialize(): void
 *  {
 *      // Test passes if method doesn't throw
 *  }
 * ```
 *
 * Custom assertions with Closure (PHP 8.5+)
 *
 * ```
 *  #[TestInline([10, 3], fn($r) => Assert::greaterThan(3, $r) )]
 *  public function divide(int $a, int $b): float
 *  {
 *      return $a / $b;
 *  }
 * ```
 *
 * Multiple assertions in Closure
 *
 * ```
 *  #[TestInline(
 *      arguments: ['john.doe@example.com'],
 *      result: function (User $user) {
 *          Assert::same('john.doe@example.com', $user->email);
 *          Assert::true($user->isActive);
 *      },
 *  )]
 *  public function createUser(string $email): User
 * ```
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(InlineInterceptor::class)]
final readonly class TestInline implements Interceptable
{
    /**
     * @param array $arguments Positional arguments to pass to the method/function
     * @param mixed|\Closure(mixed): mixed $result Expected result value or custom assertion closure.
     *        - If a scalar/array/enum: strict equality check using {@see Assert::same()}
     *        - If Closure (PHP 8.5+): receives actual result, perform custom assertions inside
     *        - If null: indicates void or null return type
     */
    public function __construct(
        public array $arguments,
        public mixed $result = null,
    ) {}
}
