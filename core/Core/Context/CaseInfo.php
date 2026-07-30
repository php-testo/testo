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

        # The address takes the class FQN, not `$definition->name` — that one is the short name, which
        # reads well in a report but does not identify the class. A case of free functions has no class,
        # and is named by its file instead.
        $this->identity = ($suite ?? new SuiteIdentity('undefined'))
            ->toCase($definition->reflection?->getName(), $definition->type, self::fileOf($definition));
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

    /**
     * File to put on the address, in one spelling whatever it came from.
     *
     * A located case already carries a path normalized to `/`; a case built by hand carries none, and
     * the class it reflects reports the OS separator. Left as two spellings the field would name the
     * same file two ways depending on whether the case has a class.
     *
     * @return non-empty-string|null
     */
    private static function fileOf(CaseDefinition $definition): ?string
    {
        $file = $definition->file ?? $definition->reflection?->getFileName();

        if ($file === false || $file === null) {
            return null;
        }

        $normalized = \str_replace('\\', '/', $file);
        \assert($normalized !== '');

        return $normalized;
    }
}
