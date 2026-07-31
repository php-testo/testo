<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

use Internal\Path;
use Testo\Core\Context\TestInfo;
use Testo\Core\Value\TestType;

/**
 * @api
 */
final readonly class CaseDefinition
{
    public function __construct(
        public ?string $name,

        /**
         * @var non-empty-string Type of the test case, e.g. 'test', 'unit', 'inline', 'bench', etc.
         * @see TestType
         */
        public string $type,
        public Path $file,
        public ?\ReflectionClass $reflection = null,
        public TestDefinitions $tests = new TestDefinitions(),

        /**
         * @var null|\Closure(TestInfo): mixed Handler for executing the test methods in this case.
         */
        public ?\Closure $handler = null,
    ) {}

    public function with(
        ?string $name = null,
        ?TestDefinitions $tests = null,
        ?\Closure $handler = null,
    ): self {
        return new self(
            $name ?? $this->name,
            $this->type,
            $this->file,
            $this->reflection,
            $tests ?? $this->tests,
            $handler ?? $this->handler,
        );
    }

    public function getName(): string
    {
        return ($this->name ?? 'undefined') . " [{$this->type}]";
    }
}
