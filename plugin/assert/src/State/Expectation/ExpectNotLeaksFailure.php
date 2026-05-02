<?php

declare(strict_types=1);

namespace Testo\Assert\State\Expectation;

use Testo\Expect;

/**
 * Failure for the {@see Expect::notLeaks()} expectation.
 */
final class ExpectNotLeaksFailure extends ExpectationFailed
{
    /**
     * @param array<array-key, \WeakReference<object>> $map The map of tracked objects.
     */
    public static function fromWeakReferences(array $map, string $message): self
    {
        # Collect all records from the map
        $records = [];

        $cnt = 0;
        foreach ($map as $k => $ref) {
            $obj = $ref->get();
            $leaked = $obj !== null;
            $leaked and ++$cnt;
            $state = $leaked ? 'x' : ' ';
            $class = $obj !== null ? $obj::class : '(unknown)';
            $records[] = \sprintf("- [%s] %s", $state, \is_string($k) ? $k : $class);
        }

        $m = \count($map) === 1 ? 'object is' : 'objects are';
        return new self(
            expectation: "the provided $m garbage collected",
            context: $message,
            reason: $cnt === 1
                ? 'the object was not garbage collected'
                : \sprintf(
                    '%d of %d objects were not garbage collected',
                    $cnt,
                    \count($map),
                ),
            details: "Leaked objects:\n" . \implode("\n", $records),
        );
    }
}
