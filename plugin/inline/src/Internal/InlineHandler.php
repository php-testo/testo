<?php

declare(strict_types=1);

namespace Testo\Inline\Internal;

use Testo\Assert;
use Testo\Core\Context\TestInfo;
use Testo\Inline\Internal\Exception\TestInlineAttributeMissingException;
use Testo\Inline\TestInline;

/**
 * @internal
 * @psalm-internal Testo\Inline
 */
final readonly class InlineHandler
{
    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(TestInline::class);
        $attr instanceof TestInline or throw TestInlineAttributeMissingException::fromTestInfo($info);

        # Execute the method
        $result = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
            ? $info->testDefinition->reflection->invoke(null, ...$info->arguments)
            : $info->testDefinition->reflection->invoke($info->caseInfo->instance->getInstance(), ...$info->arguments);

        # Verify the expected result
        if ($attr->result instanceof \Closure) {
            ($attr->result)($result);
            return $result;
        }

        Assert::same($result, $attr->result);
        return $result;
    }
}
