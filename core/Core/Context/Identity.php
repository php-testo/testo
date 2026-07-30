<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Internal\RuntimeSequence;

/**
 * Address of one node in a run: a suite, a case, a test, or one data set of a test.
 *
 * Each level is its own type and declares exactly the fields it has — {@see Identity\SuiteIdentity},
 * {@see Identity\CaseIdentity}, {@see Identity\TestIdentity} — so an address never carries a field
 * that does not apply to it, and nothing has to be read as "absent because this level does not go
 * that deep". The fields are plain scalars: no level references the one above it, so reading any part
 * of an address never walks a chain of objects.
 *
 * Step down with {@see Identity\SuiteIdentity::toCase()}, {@see Identity\CaseIdentity::toTestIdentity()}
 * and {@see Identity\TestIdentity::with()}.
 *
 * Two things live on an address, and they answer different questions:
 * - the fields (and `__toString()`) say *which* node this is, and stay the same across runs;
 * - {@see $randomId} says *which run of it* is in flight, and means nothing outside this process.
 *
 * @api
 */
abstract readonly class Identity implements \Stringable
{
    /**
     * Number correlating everything one in-flight run emits — its events, its captured output, its
     * report block. Process-local and not part of the address: never persist it, never match on it,
     * and expect a different number for the same test on the next run.
     *
     * Repeats and retries of one test share it, since they are one run being re-attempted. A data set
     * shares its batch's, since it is a phase of that run rather than a run of its own.
     *
     * @var int<1, max>
     */
    public int $randomId;

    public function __construct()
    {
        $this->randomId = RuntimeSequence::next();
    }

    /**
     * The address in the form the outside world writes it: what `--filter` accepts and what TeamCity
     * puts in a `locationHint`.
     *
     * Deliberately narrower than {@see __toString()}. This names *code*, so it carries neither the
     * suite nor the type — the same class runs under any suite, and both are filtered separately
     * (`--suite`, `--type`). The result is meant to be parsed, not read.
     *
     * Null where the level names no code at all: a case of free functions has no class to qualify,
     * and nothing else about it belongs in an FQN. The levels that always have one narrow the return
     * type back to `string`.
     *
     * @return non-empty-string|null
     */
    abstract public function fqn(): ?string;
}
