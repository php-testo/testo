<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Output\Rendering\StackTrace;
use Tests\Output\Stub\AssertMethodStub;
use Tests\Output\Stub\MiddlewareStub;
use Tests\Output\Stub\ThrowingStub;

final class StackTraceTest
{
    public function testEmptyTraceReturnsEmpty(): void
    {
        // Act & Assert
        Assert::same([], StackTrace::cutStackTrace([]));
    }

    public function testCutsFramesBelowAssertMethodFromExceptionTrace(): void
    {
        // Arrange
        try {
            AssertMethodStub::run(ThrowingStub::fail(...));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: AssertMethodStub::run is the first frame, ThrowingStub::fail is removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(AssertMethodStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $hasThrowingFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === ThrowingStub::class,
        );
        Assert::same([], $hasThrowingFrames);
    }

    public function testCutsFramesBelowAssertMethodFromDebugBacktrace(): void
    {
        // Arrange
        $trace = AssertMethodStub::run(ThrowingStub::captureTrace(...));

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: AssertMethodStub::run is the first frame, ThrowingStub::captureTrace is removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(AssertMethodStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $hasThrowingFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === ThrowingStub::class,
        );
        Assert::same([], $hasThrowingFrames);
    }

    public function testDoesNotAssertMethodWithoutAttribute(): void
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

    public function testCutsAtOutermostAssertMethodWithMultipleAttributes(): void
    {
        // Arrange: outer AssertMethod -> closure -> inner AssertMethod -> fail
        try {
            AssertMethodStub::run(static fn() => AssertMethodStub::run(ThrowingStub::fail(...)));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: outer AssertMethod is the first frame, inner AssertMethod and deeper are removed
        Assert::true(\count($result) < \count($trace));
        Assert::same(AssertMethodStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
        $cutFrames = \array_filter(
            $result,
            static fn(array $f): bool => ($f['class'] ?? null) === AssertMethodStub::class,
        );
        Assert::same(1, \count($cutFrames));
    }

    public function testDoesNotCutBeyondDepthLimit(): void
    {
        // Arrange: AssertMethod is beyond SEARCH_DEPTH from the start
        try {
            AssertMethodStub::run(static fn() => MiddlewareStub::runDeep(ThrowingStub::fail(...)));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }

        // Act
        $result = StackTrace::cutStackTrace($trace);

        // Assert: AssertMethod too far from the error, nothing is cut
        Assert::same($trace, $result);
    }

    public function testBoundaryStopsAssertMethodSearch(): void
    {
        // Arrange: AssertMethod -> middleware -> boundary (no AssertMethod between error and boundary)
        try {
            AssertMethodStub::run(static fn() => MiddlewareStub::run(ThrowingStub::fail(...)));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }
        $boundary = new \ReflectionMethod(MiddlewareStub::class, 'run');

        // Act: boundary stops search before AssertMethod is reached
        $result = StackTrace::cutStackTrace($trace, $boundary, false);

        // Assert: trace unchanged — AssertMethod is after boundary, not found
        Assert::same($trace, $result);
    }

    public function testBoundaryWithAssertMethodBeforeBoundary(): void
    {
        // Arrange: error -> AssertMethod -> middleware -> boundary
        try {
            MiddlewareStub::run(
                static fn() => AssertMethodStub::run(ThrowingStub::fail(...)),
            );
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }
        $boundary = new \ReflectionMethod(MiddlewareStub::class, 'run');

        // Act
        $result = StackTrace::cutStackTrace($trace, $boundary);

        // Assert: AssertMethod found before boundary, internal frames cut
        Assert::same(AssertMethodStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
    }

    public function testBoundaryBypassesDepthLimit(): void
    {
        // Arrange: AssertMethod is beyond SEARCH_DEPTH but before boundary
        try {
            MiddlewareStub::run(static fn() => AssertMethodStub::run(
                static fn() => MiddlewareStub::runDeep(ThrowingStub::fail(...)),
            ));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }
        $boundary = new \ReflectionMethod(MiddlewareStub::class, 'run');

        // Act
        $result = StackTrace::cutStackTrace($trace, $boundary);

        // Assert: AssertMethod found despite being beyond SEARCH_DEPTH
        Assert::same(AssertMethodStub::class, $result[0]['class']);
        Assert::same('run', $result[0]['function']);
    }

    public function testTrimAtBoundaryCutsFramesAfterBoundary(): void
    {
        // Arrange
        try {
            MiddlewareStub::run(ThrowingStub::fail(...));
        } catch (\RuntimeException $e) {
            $trace = $e->getTrace();
        }
        $boundary = new \ReflectionMethod(MiddlewareStub::class, 'run');

        // Act
        $result = StackTrace::cutStackTrace($trace, $boundary, trimAtBoundary: true);

        // Assert: trace ends at the boundary method
        Assert::true(\count($result) < \count($trace));
        $lastFrame = $result[\count($result) - 1];
        Assert::same(MiddlewareStub::class, $lastFrame['class']);
        Assert::same('run', $lastFrame['function']);
    }
}
