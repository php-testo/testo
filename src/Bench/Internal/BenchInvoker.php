<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\BenchWith;
use Testo\Bench\Exception\BenchWithAttributeMissingException;
use Testo\Core\Context\TestInfo;

final class BenchInvoker
{
    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(BenchWith::class);
        $attr instanceof BenchWith or throw BenchWithAttributeMissingException::fromTestInfo($info);

        # TODO: bench
        # Execute the method
        $result = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
            ? $info->testDefinition->reflection->invoke(null, ...$info->arguments)
            : $info->testDefinition->reflection->invoke($info->caseInfo->instance->getInstance(), ...$info->arguments);

        return $result;
    }
}
