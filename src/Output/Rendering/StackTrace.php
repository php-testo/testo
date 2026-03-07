<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

/**
 * @internal
 */
final class StackTrace
{
    /** Max number of frames to scan for {@see CutTrace} before giving up. Resets on each match. */
    private const SEARCH_DEPTH = 3;

    /** @var array<string, bool> Cached {@see CutTrace} attribute lookup results */
    private static array $cutTraceCache = [];

    /**
     * @param list<array<string, mixed>> $trace
     * @param \ReflectionFunctionAbstract|null $boundary Test function — stops CutTrace search at this frame.
     * @param bool $trimAtBoundary If true, also removes all frames after the boundary.
     * @return list<array<string, mixed>>
     */
    public static function cutStackTrace(
        array $trace,
        ?\ReflectionFunctionAbstract $boundary = null,
        bool $trimAtBoundary = true,
    ): array {
        $cutIndex = null;
        /** @var list<string> $uncached Keys of frames checked but not yet cached */
        $uncached = [];
        $boundaryName = $boundary?->getName();
        $boundaryClass = $boundary instanceof \ReflectionMethod
            ? $boundary->getDeclaringClass()->getName()
            : null;
        $limit = $boundary !== null
            ? \count($trace)
            : \min(\count($trace), self::SEARCH_DEPTH);

        for ($index = 0; $index < $limit; $index++) {
            $frame = $trace[$index];
            $class = $frame['class'] ?? null;
            $function = $frame['function'] ?? null;

            // Boundary reached — stop searching for CutTrace
            if ($boundaryName !== null and $function === $boundaryName
                and ($boundaryClass === null ? $class === null : $class === $boundaryClass)
            ) {
                $trimAtBoundary and $trace = \array_slice($trace, 0, $index + 1);
                break;
            }

            if ($class === null or $function === null) {
                continue;
            }

            $key = $class . '::' . $function;

            if (self::$cutTraceCache[$key] ?? self::resolveHasCutTrace($key, $class, $function)) {
                $cutIndex = $index;
                $boundary !== null or $limit = \min(\count($trace), $index + 1 + self::SEARCH_DEPTH);
                // Cache preceding uncached frames as false
                foreach ($uncached as $k) {
                    self::$cutTraceCache[$k] = false;
                }
                $uncached = [];
            } elseif (!isset(self::$cutTraceCache[$key])) {
                $uncached[] = $key;
            }
        }

        return $cutIndex !== null
            ? \array_slice($trace, $cutIndex)
            : $trace;
    }

    private static function resolveHasCutTrace(string $key, string $class, string $function): bool
    {
        try {
            $method = new \ReflectionMethod($class, $function);
        } catch (\ReflectionException) {
            return false;
        }

        if ($method->getAttributes(CutTrace::class) === []) {
            return false;
        }

        self::$cutTraceCache[$key] = true;

        return true;
    }
}
