<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

final readonly class BenchResult {
    public function __construct(
        /**
         * @var list<Round> List of rounds, each containing results for all cases.
         */
        public array $iterations,

        /**
         * @var list<string|int> Aliases for case names, if any.
         */
        public array $aliases,

        /**
         * @var list<Line> Res
         */
        public array $explanation,
    ) {}
}
