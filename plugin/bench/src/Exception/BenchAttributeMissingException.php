<?php

declare(strict_types=1);

namespace Testo\Bench\Exception;

use Testo\Bench;
use Testo\Core\Context\TestInfo;

/**
 * Thrown when {@see \Testo\Bench} attribute is missing in {@see BenchHandler}.
 *
 * This indicates a broken pipeline where the {@see \Testo\Bench\Internal\Pipeline\BenchInterceptor} middleware
 * did not execute as expected. The {@see \Testo\Bench} attribute should have been set
 * by the interceptor before reaching the invoker.
 *
 * @internal
 */
final class BenchAttributeMissingException extends \LogicException
{
    public static function fromTestInfo(TestInfo $info): self
    {
        return new self(
            \sprintf(
                'Target `Bench` attribute is missing for `%s%s()`. This indicates a broken test pipeline.',
                $info->caseInfo->definition->reflection === null
                    ? ''
                    : $info->caseInfo->definition->reflection->getName() . '::',
                $info->testDefinition->reflection->getName(),
            ),
        );
    }
}
