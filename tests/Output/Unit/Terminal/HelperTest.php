<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Output\Terminal\Renderer\Helper;
use Testo\Test;

#[Test]
#[Covers(Helper::class)]
final class HelperTest
{
    public function rendersClassMessageFileAndStackTraceWithoutCodeWhenZero(): void
    {
        $throwable = self::throwViaBoundary();

        $out = Helper::formatException($throwable, self::boundaryReflection());

        Assert::string($out)
            ->contains('RuntimeException: boom')
            ->contains('File: ')
            ->contains(':' . $throwable->getLine())
            ->contains('Stack trace:')
            ->contains('#0 ')
            // frame carrying a class renders as Class::method()
            ->contains(self::class . '::boundaryMarker()')
            // a zero code must not emit a "(Code: N)" segment
            ->notContains('(Code:');
    }

    public function includesCodeSegmentWhenNonZero(): void
    {
        $throwable = self::throwViaBoundary(7);

        $out = Helper::formatException($throwable, self::boundaryReflection());

        Assert::string($out)->contains('RuntimeException: boom (Code: 7)');
    }

    public function omitsStackTraceBlockWhenTraceIsEmpty(): void
    {
        $throwable = self::withEmptyTrace(new \RuntimeException('no-frames'));

        // strlen never appears in the trace, so the boundary leaves the (empty) trace as-is.
        $out = Helper::formatException($throwable, new \ReflectionFunction('strlen'));

        Assert::string($out)
            ->contains('RuntimeException: no-frames')
            ->contains('File: ')
            ->notContains('Stack trace:');
    }

    public function rendersInternalFunctionAndBareFunctionFramesWithNumbering(): void
    {
        $throwable = self::throwViaArrayMap();

        // strlen is absent from the trace, so both native frames survive the cut.
        $out = Helper::formatException($throwable, new \ReflectionFunction('strlen'));

        Assert::string($out)
            ->contains('LogicException: via-map')
            // a frame without a file renders "[internal function]" as its location
            ->contains('#0 [internal function]: ')
            // a class-less frame renders as a bare function() call
            ->contains('array_map()')
            // frames are numbered sequentially
            ->contains('#1 ');
    }

    #[DataSet([0, 0], 'depth 0 stops before any previous')]
    #[DataSet([1, 1], 'depth 1 includes the deepest allowed link')]
    #[DataSet([2, 2], 'depth 2 walks the full chain')]
    #[DataSet([99, 2], 'a limit beyond the chain stops at its real end')]
    public function walksPreviousChainUpToDepth(int $maxPreviousDepth, int $expectedCausedBy): void
    {
        $chain = new \Exception('outer', 0, new \Exception('mid', 0, new \Exception('inner')));

        $out = Helper::formatException($chain, new \ReflectionFunction('strlen'), $maxPreviousDepth);

        Assert::same(\substr_count($out, 'Caused by:'), $expectedCausedBy);
    }

    public function deepestAllowedPreviousLinkIsIncludedAtItsLimit(): void
    {
        $chain = new \Exception('outer', 0, new \Exception('mid', 0, new \Exception('inner')));

        // depth 1 reaches "mid" (the first previous) but not "inner" (the second).
        $out = Helper::formatException($chain, new \ReflectionFunction('strlen'), 1);

        Assert::string($out)
            ->contains('Exception: mid')
            ->notContains('Exception: inner');
    }

    /**
     * Boundary method that appears in the trace of {@see throwViaBoundary}. Kept private so
     * Testo's finder does not mistake it for a test method; {@see StackTrace::cutStackTrace}
     * still matches it by class + name regardless of visibility.
     */
    private static function boundaryMarker(callable $callback): mixed
    {
        return $callback();
    }

    private static function boundaryReflection(): \ReflectionMethod
    {
        return new \ReflectionMethod(self::class, 'boundaryMarker');
    }

    private static function throwViaBoundary(int $code = 0): \Throwable
    {
        try {
            self::boundaryMarker(static fn(): never => throw new \RuntimeException('boom', $code));
        } catch (\Throwable $e) {
            return $e;
        }

        throw new \LogicException('unreachable');
    }

    /**
     * Produces a trace whose first frame is a native `[internal function]` call (the closure
     * invoked by `array_map`) and whose next frame is the class-less `array_map` function.
     */
    private static function throwViaArrayMap(): \Throwable
    {
        try {
            \array_map(static fn(): never => throw new \LogicException('via-map'), [1]);
        } catch (\Throwable $e) {
            return $e;
        }

        throw new \LogicException('unreachable');
    }

    /**
     * Clears a throwable's captured trace, reproducing an exception created at `{main}` scope
     * (empty trace) so the "no stack trace" branch of the formatter is reachable from a test.
     */
    private static function withEmptyTrace(\Throwable $throwable): \Throwable
    {
        $trace = new \ReflectionProperty(\Exception::class, 'trace');
        $trace->setValue($throwable, []);

        return $throwable;
    }
}
