<?php

declare(strict_types=1);

namespace Testo\Core\Internal;

use Testo\Core\Context\TestInfo;

/**
 * Default test invoker.
 */
final readonly class DefaultTestHandler
{
    public function __invoke(TestInfo $info): mixed
    {
        $reflection = $info->testDefinition->reflection;
        if ($reflection instanceof \ReflectionFunction) {
            return $reflection->invoke(...$info->arguments);
        }
        /** @var \ReflectionMethod $reflection */
        return $info->caseInfo->instance === null || $reflection->isStatic()
            ? $reflection->invoke(null, ...$info->arguments)
            : $reflection->invoke($info->caseInfo->instance->getInstance(), ...$info->arguments);
    }
}
