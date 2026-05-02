<?php

declare(strict_types=1);

namespace Testo\Codecov;

/**
 * Restricts which source code is counted for coverage by this test.
 *
 * Only lines belonging to the specified classes, traits, enums, methods, or functions
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
 * ## Covering a trait
 *
 * All executable lines of the trait are included:
 *
 * ```
 *  #[Covers(Cacheable::class)]
 *  public function testCacheableBehavior(): void { ... }
 * ```
 *
 * ## Covering an enum
 *
 * All executable lines of the enum are included:
 *
 * ```
 *  #[Covers(OrderStatus::class)]
 *  public function testOrderStatusTransitions(): void { ... }
 * ```
 *
 * ## Covering a specific method
 *
 * Only lines of the given method are included.
 * Works with classes, traits, and enums:
 *
 * ```
 *  #[Covers(UserService::class, 'create')]
 *  public function testCreateUser(): void { ... }
 *
 *  #[Covers(Cacheable::class, 'invalidate')]
 *  public function testCacheInvalidation(): void { ... }
 *
 *  #[Covers(OrderStatus::class, 'canTransitionTo')]
 *  public function testStatusTransition(): void { ... }
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
     * @param class-string|non-empty-string $classOrFunction Fully qualified class, trait, enum, or function name.
     * @param non-empty-string|null $method Method name within the class/trait/enum. When specified, only that method is covered.
     */
    public function __construct(
        public string $classOrFunction,
        public ?string $method = null,
    ) {}
}
