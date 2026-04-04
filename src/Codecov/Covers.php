<?php

declare(strict_types=1);

namespace Testo\Codecov;

/**
 * Restricts which source code is counted for coverage by this test.
 *
 * Only lines belonging to the specified classes, methods, or functions
 * will be included in the coverage result. Everything else is discarded.
 *
 * The attribute is repeatable — multiple `#[Covers]` on the same target are combined.
 * When placed on a class, applies to all test methods in that class.
 *
 * ## Covering a class
 *
 * All executable lines of the class are included:
 *
 * ```
 *  #[Covers(UserService::class)]
 *  public function testCreateUser(): void { ... }
 * ```
 *
 * ## Covering a specific method
 *
 * Only lines of the given method are included:
 *
 * ```
 *  #[Covers(UserService::class, 'create')]
 *  public function testCreateUser(): void { ... }
 * ```
 *
 * ## Covering a function
 *
 * ```
 *  #[Covers('App\Helpers\format_name')]
 *  public function testFormatName(): void { ... }
 * ```
 *
 * ## Multiple targets
 *
 * ```
 *  #[Covers(UserService::class)]
 *  #[Covers(UserRepository::class, 'findById')]
 *  public function testCreateUser(): void { ... }
 * ```
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Covers implements Internal\CoverageAttribute
{
    /**
     * @param class-string|non-empty-string $classOrFunction Fully qualified class name or function name.
     * @param non-empty-string|null $method Method name within the class. When specified, only that method is covered.
     */
    public function __construct(
        public string $classOrFunction,
        public ?string $method = null,
    ) {}
}
