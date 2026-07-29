<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Internal\Attributed;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Internal\DefaultTestHandler;
use Testo\Core\Value\CaseInstance;

/**
 * Information about run test case.
 *
 * @api
 */
final readonly class CaseInfo
{
    use Attributed;

    public string $name;

    /**
     * Address of this case within its suite — what the tests of the case descend from.
     */
    public CaseIdentity $identity;

    /**
     * Handler for executing the test method.
     *
     * @var \Closure(TestInfo): mixed
     */
    public \Closure $handler;

    /**
     * @param ?CaseInstance $instance Test Case class instance if class is defined, null otherwise.
     * @param array<non-empty-string, mixed> $attributes
     * @param callable(TestInfo): mixed $handler Invoker for the test method.
     * @param ?\Closure(list<callable(): TestResult>): list<TestResult> $batchRunner Runner that drives
     *        this case's tests (given the per-test handlers); null uses the default sequential run.
     * @param SuiteIdentity|null $suite Suite this case belongs to. Omitting it stands the case on a
     *        suite of its own — for a case built outside a run (tests, tooling), whose address has no
     *        real suite to name.
     */
    public function __construct(
        public CaseDefinition $definition,
        public ?CaseInstance $instance = null,
        public array $attributes = [],
        callable $handler = new DefaultTestHandler(),
        public ?\Closure $batchRunner = null,
        ?SuiteIdentity $suite = null,
    ) {
        $this->name = $definition->getName();
        $this->handler = $handler(...);

        # A definition may be nameless (a file of free functions being one); the address still needs
        # something to say, and `getName()` already settles on the same placeholder.
        $case = $definition->name === null || $definition->name === '' ? 'undefined' : $definition->name;
        $this->identity = ($suite ?? new SuiteIdentity('undefined'))->toCase($case, $definition->type);
    }

    public function with(
        ?\Closure $handler = null,
    ): self {
        # Clone rather than rebuild: re-running the constructor would mint a second address for a case
        # that is still the same one, and the tests already descended from the first.
        return $this->cloneWith('handler', $handler ?? $this->handler);
    }

    /**
     * Replaces the case instance provider.
     */
    public function withInstance(?CaseInstance $instance): self
    {
        /** @see self::$instance */
        return $this->cloneWith('instance', $instance);
    }

    /**
     * Sets the runner that drives this case's tests (e.g. a fiber scheduler). Null keeps the default.
     *
     * @param ?callable(list<callable(): TestResult>): list<TestResult> $batchRunner
     */
    public function withBatchRunner(?callable $batchRunner): self
    {
        /** @see self::$batchRunner */
        return $this->cloneWith('batchRunner', $batchRunner === null ? null : $batchRunner(...));
    }
}
