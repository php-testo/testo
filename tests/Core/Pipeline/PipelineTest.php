<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Interceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Pipeline;
use Testo\Test;

#[Test]
#[Covers(Pipeline::class)]
final class PipelineTest
{
    public function prepareWithIncludeTypeKeepsMatchingInterceptor(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']), new PipelineTagAInterceptor());
        $result = $pipeline->with(static fn(object $input): string => 'last', 'testMethod')(new \stdClass());

        Assert::same($result, 'A>last');
    }

    public function prepareWithEmptyFilterKeepsAllInterceptors(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(), new PipelineUnitOnlyInterceptor());
        $result = $pipeline->with(static fn(object $input): string => 'last', 'testMethod')(new \stdClass());

        Assert::same($result, 'unit-only>last');
    }

    public function prepareFiltersInterceptorByIncludeType(): void
    {
        $interceptor = new PipelineUnitOnlyInterceptor();
        $last = static fn(object $input): string => 'last';

        $matching = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']), $interceptor)
            ->with($last, 'testMethod')(new \stdClass());
        $nonMatching = Pipeline::prepare(new PipeOptions(includeTypes: ['integration']), $interceptor)
            ->with($last, 'testMethod')(new \stdClass());

        Assert::same($matching, 'unit-only>last');
        Assert::same($nonMatching, 'last');
    }

    public function withProducesCallableThatThreadsTerminalResult(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagAInterceptor());

        $callable = $pipeline->with(static fn(object $input): string => 'terminal', 'testMethod');
        $result = $callable(new \stdClass());

        Assert::same($result, 'A>terminal');
    }

    public function invokeCallsLastCallableWhenNoInterceptors(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions());

        $result = $pipeline->with(static fn(object $input): string => 'last_result', 'testMethod')(new \stdClass());

        Assert::same($result, 'last_result');
    }

    public function invokeThreadsTerminalResultThroughInterceptor(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagAInterceptor());

        $result = $pipeline->with(static fn(object $input): string => 'last_result', 'testMethod')(new \stdClass());

        Assert::same($result, 'A>last_result');
    }

    public function invokeRunsDistinctInterceptorsInOrder(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagBInterceptor(), new PipelineTagAInterceptor());

        $result = $pipeline->with(static fn(object $input): string => 'last', 'testMethod')(new \stdClass());

        Assert::same($result, 'A>B>last');
    }

    public function combineAddsDistinctInterceptorSoBothRun(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagAInterceptor());

        $combined = $pipeline->combine(new PipelineTagBInterceptor());
        $result = $combined->with(static fn(object $input): string => 'last', 'testMethod')(new \stdClass());

        Assert::same($result, 'A>B>last');
    }

    public function combineLeavesOriginalPipelineUntouched(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagAInterceptor());
        $last = static fn(object $input): string => 'last';

        $pipeline->combine(new PipelineTagBInterceptor());
        $result = $pipeline->with($last, 'testMethod')(new \stdClass());

        Assert::same($result, 'A>last');
    }

    public function combineKeepsOnlyUnconsumedInterceptorsThenAddsNew(): void
    {
        $capturer = new PipelineNextCapturingInterceptor();
        $base = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),$capturer, new PipelineTagBInterceptor());

        // Running the capturer advances the chain: it stores the $next pipeline (current = 1,
        // i.e. positioned at B) without consuming further interceptors itself.
        $base->with(static fn(object $input): string => 'unused', 'testMethod')(new \stdClass());
        $advanced = $capturer->captured ?? throw new \RuntimeException('Pipeline did not advance.');

        // Combining on the advanced pipeline must drop the already-consumed capturer,
        // keep the un-consumed B, and add the new C.
        $combined = $advanced->combine(new PipelineTagCInterceptor());
        $result = $combined->with(static fn(object $input): string => 'last', 'testMethod')(new \stdClass());

        Assert::same($result, 'B>C>last');
    }

    public function withRetargetsTerminalHandlerAndMethod(): void
    {
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']),new PipelineTagAInterceptor());

        $first = $pipeline->with(static fn(object $input): string => 'one', 'testMethod')(new \stdClass());
        $second = $pipeline->with(static fn(object $input): string => 'two', 'testMethod')(new \stdClass());

        Assert::same($first, 'A>one');
        Assert::same($second, 'A>two');
    }
}

#[InterceptorOptions(order: 100)]
final class PipelineTagAInterceptor implements Interceptor
{
    public function testMethod(object $input, callable $next): string
    {
        return 'A>' . $next($input);
    }
}

#[InterceptorOptions(order: 200)]
final class PipelineTagBInterceptor implements Interceptor
{
    public function testMethod(object $input, callable $next): string
    {
        return 'B>' . $next($input);
    }
}

#[InterceptorOptions(order: 300)]
final class PipelineTagCInterceptor implements Interceptor
{
    public function testMethod(object $input, callable $next): string
    {
        return 'C>' . $next($input);
    }
}

#[InterceptorOptions(testType: 'unit')]
final class PipelineUnitOnlyInterceptor implements Interceptor
{
    public function testMethod(object $input, callable $next): string
    {
        return 'unit-only>' . $next($input);
    }
}

/**
 * Captures the downstream pipeline ({@see Pipeline::next()}) it receives so a test can
 * obtain a pipeline whose internal chain has already been advanced past this interceptor.
 * Used to exercise {@see Pipeline::combine()} slicing of already-consumed interceptors.
 */
#[InterceptorOptions(order: 50)]
final class PipelineNextCapturingInterceptor implements Interceptor
{
    public ?Pipeline $captured = null;

    public function testMethod(object $input, callable $next): string
    {
        \assert($next instanceof Pipeline);
        $this->captured = $next;

        return (string) $next($input);
    }
}
