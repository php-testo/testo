<?php

declare(strict_types=1);

namespace Testo\Inline\Internal\Exception;

use Testo\Core\Context\TestInfo;
use Testo\Inline\Internal\InlineHandler;
use Testo\Inline\Internal\InlineInterceptor;
use Testo\Inline\TestInline;

/**
 * Thrown when {@see TestInline} attribute is missing in {@see InlineHandler}.
 *
 * This indicates a broken pipeline where the TestInlineInterceptor middleware
 * did not execute as expected. The TestInline attribute should have been set
 * by the interceptor before reaching the invoker.
 *
 * Possible causes:
 * - The {@see InlineHandler} was assigned to a test case incorrectly.
 * - Another interceptor in the chain overwrote or removed the {@see TestInline} attribute.
 * - The {@see InlineInterceptor} was not registered or failed to execute.
 *
 * @internal
 * @psalm-internal Testo\Inline
 */
final class TestInlineAttributeMissingException extends \LogicException
{
    public static function fromTestInfo(TestInfo $info): self
    {
        return new self(
            \sprintf(
                'Target TestInline attribute is missing for `%s%s()`. This indicates a broken test pipeline.',
                $info->caseInfo->definition->reflection === null
                    ? ''
                    : $info->caseInfo->definition->reflection->getName() . '::',
                $info->testDefinition->reflection->getName(),
            ),
        );
    }
}
