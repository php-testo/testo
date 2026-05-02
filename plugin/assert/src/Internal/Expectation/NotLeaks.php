<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Expectation;

use Testo\Assert\State\Expectation\ExpectationFulfilled;
use Testo\Assert\State\Expectation\ExpectNotLeaksFailure;
use Testo\Assert\TestState;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;

/**
 * Assert that no memory leaks occurred for the given objects.
 *
 * @see Expect::notLeaks()
 * @internal
 * @psalm-internal Testo\Assert
 */
final class NotLeaks
{
    /** @var \WeakReference[] */
    private readonly array $map;

    private string $message = '';

    public function __construct(
        object ...$objects,
    ) {
        $this->map = \array_map(static fn(object $object): \WeakReference => \WeakReference::create($object), $objects);
    }

    /**
     * Set an optional description for the expectation.
     */
    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Evaluate the expectation.
     *
     * @internal
     */
    public function __invoke(TestResult $result, TestState $state): TestResult
    {
        $r = \array_filter($this->map, static fn(\WeakReference $ref): bool => $ref->get() !== null);

        if ($r === []) {
            $state->history[] = new ExpectationFulfilled(
                \sprintf(
                    '%d %s not leaked',
                    \count($this->map),
                    \count($this->map) === 1 ? 'object was' : 'objects were',
                ),
                $this->message,
            );
            return $result;
        }

        $e = ExpectNotLeaksFailure::fromWeakReferences($r, $this->message);
        $state->history[] = $e;

        return $result->with(status: Status::Failed)->withFailure($e);
    }
}
