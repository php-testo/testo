<?php

declare(strict_types=1);

namespace Testo\Sample\Internal;

use Testo\Assert;
use Testo\Sample\Exception\TestInlineAttributeMissingException;
use Testo\Sample\TestInline;
use Testo\Test\Dto\TestInfo;

final class InlineTestInvoker
{
    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(TestInline::class);
        $attr instanceof TestInline or throw TestInlineAttributeMissingException::fromTestInfo($info);

        # Execute the method
        $result = $info->caseInfo->instance === null
            ? $info->testDefinition->reflection->invoke(...$info->arguments)
            : $info->testDefinition->reflection->invoke($info->caseInfo->instance, ...$info->arguments);

        # Verify the expected result
        if ($attr->result instanceof \Closure) {
            ($attr->result)($result);
            return $result;
        }

        Assert::same($attr->result, $result);
        return $result;
    }
}
