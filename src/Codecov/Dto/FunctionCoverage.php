<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

/**
 * Branch and path coverage data for a single function or method.
 */
final readonly class FunctionCoverage
{
    public function __construct(
        /** @var non-empty-string Function name (e.g. 'MyClass->myMethod' or '{main}'). */
        public string $name,

        /** @var array<int<0, max>, BranchCoverage> Branches indexed by opcode start. */
        public array $branches,

        /** @var list<PathCoverage> All possible execution paths. */
        public array $paths,
    ) {}

    public function merge(self $other): self
    {
        $branches = $this->branches;
        foreach ($other->branches as $opStart => $branch) {
            $branches[$opStart] = isset($branches[$opStart])
                ? $branches[$opStart]->merge($branch)
                : $branch;
        }

        $paths = [];
        foreach ($this->paths as $i => $path) {
            $paths[] = isset($other->paths[$i])
                ? $path->merge($other->paths[$i])
                : $path;
        }

        return new self($this->name, $branches, $paths);
    }
}
