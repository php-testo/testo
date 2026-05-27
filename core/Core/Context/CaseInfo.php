<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Internal\Attributed;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Internal\DefaultTestHandler;
use Testo\Core\Value\CaseInstance;

/**
 * Information about run test case.
 *
 * @api
 */
final class CaseInfo
{
    use Attributed;

    public readonly string $name;

    /**
     * Handler for executing the test method.
     *
     * @var \Closure(TestInfo): mixed
     */
    public readonly \Closure $handler;

    /**
     * @param array<non-empty-string, mixed> $attributes
     * @param callable(TestInfo): mixed $handler Invoker for the test method.
     */
    public function __construct(
        public readonly CaseDefinition $definition,
        /**
         * Test Case class instance if class is defined, null otherwise.
         */
        public readonly ?CaseInstance $instance = null,
        array $attributes = [],
        callable $handler = new DefaultTestHandler(),
    ) {
        $this->name = $definition->getName();
        $this->attributes = $attributes;
        $this->handler = $handler(...);
    }

    public function with(
        ?\Closure $handler = null,
    ): self {
        return new self(
            definition: $this->definition,
            instance: $this->instance,
            attributes: $this->attributes,
            handler: $handler ?? $this->handler,
        );
    }

    /**
     * Replaces the case instance provider.
     */
    public function withInstance(?CaseInstance $instance): self
    {
        /** @see self::$instance */
        return $this->cloneWith('instance', $instance);
    }
}
