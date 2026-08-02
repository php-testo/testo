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
    public CaseIdentity $identity;

    /**
     * Handler for executing the test method.
     *
     * @var \Closure(TestInfo): mixed
     */
    public \Closure $handler;

    /**
     * @param SuiteIdentity $suiteIdentity Suite this case belongs to — the root its address descends from. Every
     *        case is reached through a suite, so there is no sensible stand-in to default to.
     * @param ?CaseInstance $instance Test Case class instance if class is defined, null otherwise.
     * @param array<non-empty-string, mixed> $attributes
     * @param callable(TestInfo): mixed $handler Invoker for the test method.
     * @param ?\Closure(list<callable(): TestResult>): list<TestResult> $batchRunner Runner that drives
     *        this case's tests (given the per-test handlers); null uses the default sequential run.
     */
    public function __construct(
        public CaseDefinition $definition,
        SuiteIdentity $suiteIdentity,
        public ?CaseInstance $instance = null,
        public array $attributes = [],
        callable $handler = new DefaultTestHandler(),
        public ?\Closure $batchRunner = null,
    ) {
        $this->name = $definition->getName();
        $this->handler = $handler(...);
        $this->identity = $suiteIdentity
            ->toCase($definition->reflection?->getName(), $definition->type, $definition->file);
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
