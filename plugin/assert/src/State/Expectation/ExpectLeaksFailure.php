<?php

declare(strict_types=1);

namespace Testo\Assert\State\Expectation;

use Testo\Expect;

/**
 * Failure for the {@see Expect::leaks()} expectation.
 */
final class ExpectLeaksFailure extends ExpectationFailed
{
    /**
     * @param array<array-key, array{0: class-string, 1: \WeakReference}> $map The original map of weak references
     */
    public static function fromClassArray(array $map, string $message): self
    {
        # Collect all records from the map
        $records = [];

        $cnt = 0;
        foreach ($map as $k => [$class, $ref]) {
            $notLeak = $ref->get() === null;
            $notLeak and ++$cnt;
            $state = $notLeak ? 'x' : ' ';
            $records[] = \sprintf("- [%s] %s", $state, \is_string($k) ? $k : $class);
        }

        $m = \count($map) === 1 ? 'object is' : 'objects are';
        return new self(
            expectation: "the provided $m memorized",
            context: $message,
            reason: $cnt === 1
                ? 'the object was garbage collected'
                : \sprintf(
                    '%d of %d objects were garbage collected',
                    $cnt,
                    \count($map),
                ),
            details: "Collected objects:\n" . \implode("\n", $records),
        );
    }
}
