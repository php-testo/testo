<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Codecov\Dto\CoverageResult;

/**
 * @internal
 */
final class Cache
{
    public function __construct(
        public CoverageResult $value,
    ) {}
}
