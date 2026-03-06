<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Output\Rendering\StackTrace;
use Tests\Output\Stub\CutTraceStub;
use Tests\Output\Stub\MiddlewareStub;
use Tests\Output\Stub\ThrowingStub;

final class StackTraceTest
{
    public function testEmptyTraceReturnsEmpty(): void
    {
        // Act & Assert
        Assert::same([], StackTrace::cutStackTrace([]));
    }

    public function testCutsFramesBelowCutTraceFromExceptionTrace(): void
    {
        // Arrange
        try {
            CutTraceStub::run(ThrowingStub::fail(...));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: CutTraceStub::run is the first frame, ThrowingStub::fail is removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(CutTraceStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $hasThrowingFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === ThrowingStub::class,
        );
        Assert::same([], $hasThrowingFrames);
    }

    public function testCutsFramesBelowCutTraceFromDebugBacktrace(): void
    {
        // Arrange
        $trace = CutTraceStub::run(ThrowingStub::captureTrace(...));

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: CutTraceStub::run is the first frame, ThrowingStub::captureTrace is removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(CutTraceStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $hasThrowingFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === ThrowingStub::class,
        );
        Assert::same([], $hasThrowingFrames);
    }

    public function testDoesNotCutTraceWithoutAttribute(): void
    {
        // Arrange
        try {
            MiddlewareStub::run(ThrowingStub::fail(...));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert
        Assert::same($trace, $result);
    }

    public function testCutsAtOutermostCutTraceWithMultipleAttributes(): void
    {
        // Arrange: outer CutTrace -> closure -> inner CutTrace -> fail
        try {
            CutTraceStub::run(static fn() => CutTraceStub::run(ThrowingStub::fail(...)));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: outer CutTrace is the first frame, inner CutTrace and deeper are removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(CutTraceStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $cutFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === CutTraceStub::class,
        );
        Assert::same(1, \count($cutFrames));
    }

    public function testDoesNotCutBeyondDepthLimit(): void
    {
        // Arrange: CutTrace is beyond SEARCH_DEPTH from the start
        try {
            CutTraceStub::run(static fn() => MiddlewareStub::runDeep(ThrowingStub::fail(...)));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: CutTrace too far from the error, nothing is cut
        Assert::same($trace, $result);
    }
}
