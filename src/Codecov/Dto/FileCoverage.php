<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

/**
 * Code coverage data for a single source file.
 */
final readonly class FileCoverage
{
    public function __construct(
        /** @var non-empty-string Absolute path to the source file. */
        public string $path,

        /** @var array<int<0, max>, LineStatus> Line number => coverage status. */
        public array $lines,
    ) {}

    /**
     * Merges another coverage into this one.
     * A line is considered Executed if it was executed in either coverage run.
     */
    public function merge(self $other): self
    {
        $merged = $this->lines;

        foreach ($other->lines as $line => $status) {
            $merged[$line] = match (true) {
                !isset($merged[$line]) => $status,
                $merged[$line] === LineStatus::Executed,
                $status === LineStatus::Executed => LineStatus::Executed,
                default => $merged[$line],
            };
        }

        return new self($this->path, $merged);
    }
}
