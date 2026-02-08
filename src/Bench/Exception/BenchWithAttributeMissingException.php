<?php

declare(strict_types=1);

namespace Testo\Bench\Exception;

use Testo\Bench\BenchWith;
use Testo\Bench\Internal\BenchWithInterceptor;
use Testo\Core\Context\TestInfo;

/**
 * Thrown when {@see BenchWith} attribute is missing in {@see BenchInvoker}.
 *
 * This indicates a broken pipeline where the {@see BenchWithInterceptor} middleware
 * did not execute as expected. The {@see BenchWith} attribute should have been set
 * by the interceptor before reaching the invoker.
 *
 * @internal
 */
final class BenchWithAttributeMissingException extends \LogicException
{
    public static function fromTestInfo(TestInfo $info): self
    {
        return new self(
            \sprintf(
                'Target BenchWith attribute is missing for `%s%s()`. This indicates a broken test pipeline.',
                $info->caseInfo->definition->reflection === null
                    ? ''
                    : $info->caseInfo->definition->reflection->getName() . '::',
                $info->testDefinition->reflection->getName(),
            ),
        );
    }
}
