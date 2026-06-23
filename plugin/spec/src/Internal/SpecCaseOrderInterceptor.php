<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Definition\TestDefinition;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;

/**
 * Reorders the tests within a Test Case by their spec item number before the case runs.
 *
 * Uses the same {@see SpecNumberer} ordering as the generated document: items are ordered by their
 * effective number (a method-pinned number, otherwise `{section}.{source-line-rank}`), tests without
 * a spec number keep source order and fall to the end. When no numbers are involved this is a no-op.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final readonly class SpecCaseOrderInterceptor implements TestCaseRunInterceptor
{
    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $section = SpecHeaderReader::section($info->definition->reflection)?->number;

        $items = [];
        foreach ($info->definition->tests->getTests() as $name => $definition) {
            $items[$name] = [
                'number' => SpecHeaderReader::item($definition->reflection)?->number,
                'line' => $definition->reflection->getStartLine() ?: 0,
            ];
        }

        $position = \array_flip(SpecNumberer::orderKeys($items, $section));

        $info->definition->tests->sort(static fn(TestDefinition $a, TestDefinition $b): int =>
            ($position[$a->reflection->getShortName()] ?? 0) <=> ($position[$b->reflection->getShortName()] ?? 0));

        return $next($info);
    }
}
