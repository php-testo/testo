<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

final readonly class BenchResult
{
    public function __construct(
        /** @var list<CaseSet> */
        public array $cases,

        /** @var list<CaseResult> */
        public array $results,

        /** @var list<Line> Res */
        public array $lines = [],
    ) {}
}
