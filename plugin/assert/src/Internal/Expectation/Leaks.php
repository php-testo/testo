<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Expectation;

use Testo\Assert\State\Expectation\ExpectationFulfilled;
use Testo\Assert\State\Expectation\ExpectLeaksFailure;
use Testo\Assert\TestState;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * Asserts that objects are leaked (not garbage collected).
 *
 * @see Expect::leaks()
 * @internal
 * @psalm-internal Testo\Assert
 */
final class Leaks
{
    /** @var array<array-key, array{0: class-string, 1: \WeakReference}> */
    private readonly array $map;

    private string $message = '';

    public function __construct(
        object ...$objects,
    ) {
        $this->map = \array_map(static fn(object $object): array => [
            $object::class,
            \WeakReference::create($object),
        ], $objects);
    }

    /**
     * Set an optional description for the expectation.
     */
    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function __invoke(TestResult $result, TestState $state): TestResult
    {
        $fail = false;
        foreach ($this->map as $item) {
            if ($item[1]->get() === null) {
                $fail = true;
                break;
            }
        }

        if (!$fail) {
            $state->history[] = new ExpectationFulfilled(
                expectation: \sprintf(
                    '%d %s cached in memory',
                    \count($this->map),
                    \count($this->map) === 1 ? 'object is' : 'objects are',
                ),
                context: $this->message,
            );
            return $result;
        }

        $e = ExpectLeaksFailure::fromClassArray($this->map, $this->message);
        $state->history[] = $e;

        return $result->with(status: Status::Failed)->withFailure($e);
    }
}
