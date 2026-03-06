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
     * @return list<array<string, mixed>>
     */
    public static function cutStackTrace(array $trace): array
    {
        $cutIndex = null;
        $limit = \min(\count($trace), self::SEARCH_DEPTH);

        for ($index = 0; $index < $limit; $index++) {
            $frame = $trace[$index];

            if (!isset($frame['class'], $frame['function'])) {
                continue;
            }

            try {
                $method = new \ReflectionMethod($frame['class'], $frame['function']);
            } catch (\ReflectionException) {
                continue;
            }

            if ($method->getAttributes(CutTrace::class) !== []) {
                $cutIndex = $index;
                $limit = \min(\count($trace), $index + 1 + self::SEARCH_DEPTH);
            }
        }

        return $cutIndex !== null
            ? \array_slice($trace, $cutIndex)
            : $trace;
    }
}
