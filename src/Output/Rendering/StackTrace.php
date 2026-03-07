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

            try {
                $method = new \ReflectionMethod($class, $function);
            } catch (\ReflectionException) {
                continue;
            }

            if ($method->getAttributes(CutTrace::class) !== []) {
                $cutIndex = $index;
                $boundary !== null or $limit = \min(\count($trace), $index + 1 + self::SEARCH_DEPTH);
            }
        }

        return $cutIndex !== null
            ? \array_slice($trace, $cutIndex)
            : $trace;
    }
}
