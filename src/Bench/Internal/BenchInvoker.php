<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use DragonCode\Benchmark\Benchmark;
use Testo\Bench\BenchWith;
use Testo\Bench\Exception\BenchWithAttributeMissingException;
use Testo\Core\Context\TestInfo;

final class BenchInvoker
{
    private static function normalizeCallable(TestInfo $info, callable|array $callable): \Closure
    {
        $fn = $callable(...);
        return static fn (): mixed => $fn(...$info->arguments);
    }

    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(BenchWith::class);
        $attr instanceof BenchWith or throw BenchWithAttributeMissingException::fromTestInfo($info);

        // Current function callable
        $fn = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
            ? $info->testDefinition->reflection->getClosure(null)
            : $info->testDefinition->reflection->getClosure($info->caseInfo->instance->getInstance());


        $functions = [static fn (): mixed => $fn(...$info->arguments)];

        # Collect callables
        foreach ($attr->callables as $callable) {
            $f = self::normalizeCallable($info, $callable);
            $functions[] = $f;
        }


        Benchmark::start()
            ->withoutData()
            ->iterations($attr->iterations)
            ->compare(
                ...$functions,
            );

        return null;
    }
}
