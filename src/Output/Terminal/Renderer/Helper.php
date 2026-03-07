<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Output\Rendering\StackTrace;

/**
 * Helper utilities for text-based rendering (CLI, TeamCity, logs, etc.).
 *
 * @internal
 */
final class Helper
{
    /**
     * Formats a throwable into a detailed string with class, message, file, line, and stack trace.
     *
     * @param \Throwable $throwable The exception to format
     * @param \ReflectionFunctionAbstract $function Test method to cut stack trace at
     * @param int<0, max> $maxPreviousDepth Maximum depth for previous exceptions (0 = only main exception)
     */
    public static function formatException(
        \Throwable $throwable,
        \ReflectionFunctionAbstract $function,
        int $maxPreviousDepth = 0,
    ): string {
        $result = self::formatSingleThrowable($throwable, $function);

        // Process previous exceptions chain
        $previous = $throwable->getPrevious();
        $depth = 0;
        while ($previous !== null && $depth < $maxPreviousDepth) {
            $result .= "\n\nCaused by:\n" . self::formatSingleThrowable($previous, $function);
            $previous = $previous->getPrevious();
            $depth++;
        }

        return $result;
    }

    /**
     * Formats a single throwable with stack trace cut at the specified function.
     */
    private static function formatSingleThrowable(
        \Throwable $throwable,
        \ReflectionFunctionAbstract $function,
    ): string {
        $class = $throwable::class;
        $message = $throwable->getMessage();
        $code = $throwable->getCode();
        $file = $throwable->getFile();
        $line = $throwable->getLine();

        $result = "{$class}: {$message}";

        if ($code !== 0) {
            $result .= " (Code: {$code})";
        }

        $result .= "\nFile: {$file}:{$line}";

        // Get stack trace: cut internal frames (AssertMethod) and trim at test boundary
        $cutTrace = StackTrace::cutStackTrace($throwable->getTrace(), $function);

        if ($cutTrace !== []) {
            $result .= "\n\nStack trace:\n" . self::formatTraceArray($cutTrace);
        }

        return $result;
    }

    /**
     * Formats trace array into a readable string.
     *
     * @param array<int, array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array<mixed>}> $trace
     */
    private static function formatTraceArray(array $trace): string
    {
        $lines = [];
        $frameNumber = 0;

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = isset($frame['line']) ? ":{$frame['line']}" : '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            $location = $file === '[internal function]' ? $file : "{$file}{$line}";
            $call = $class !== '' ? "{$class}{$type}{$function}()" : "{$function}()";

            $lines[] = "#{$frameNumber} {$location}: {$call}";
            $frameNumber++;
        }

        return \implode("\n", $lines);
    }
}
