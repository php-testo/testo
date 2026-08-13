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
 * Step down with {@see Identity\SuiteIdentity::toCase()}, {@see Identity\CaseIdentity::toTest()}
 * and {@see Identity\TestIdentity::toDataSet()}.
 *
 * Two things live on an address, and they answer different questions:
 * - the fields say *which* node this is, and stay the same across runs;
 * - {@see $runtimeId} says *which run of it* is in flight, and means nothing outside this process.
 *   {@see $parentId} points at the run it opened inside, which is how a consumer rebuilds the tree of
 *   a run without relying on the order events happened to arrive in.
 *
 * @api
 */
abstract readonly class Identity
{
    /**
     * Number correlating everything this one in-flight run emits — its events, its captured output.
     * Process-local and not part of the address: never persist it, never match on it, and expect a
     * different number for the same test on the next run.
     *
     * One number per run, and a data set is a run: it gets its own rather than its batch's. What needs
     * a whole test held together — a report block, a TeamCity flow — groups by
     * {@see Identity\TestIdentity::$pipelineId} instead. Repeats and retries do share this number,
     * since they re-attempt one run rather than open new ones.
     *
     * @var int<1, max>
     */
    public int $runtimeId;

    /**
     * @param int<1, max>|null $parentId {@see $runtimeId} of the run this one opened inside — the suite
     *        for a case, the case for a test, the test for a data set — and `null` at a suite, which
     *        opens inside the run itself. The step-down factories pass it.
     *
     *        The one thing an address says about the level above it, and a number rather than a
     *        reference: reading it stays a field access, and a consumer building a tree of the run
     *        (an IDE's `parentNodeId`, say) reads the same field at every level instead of knowing
     *        which type it holds and where that type keeps its parent. Process-local, like the number
     *        it points at.
     *
     *        A data set's parent is its test, so there it holds the same number as
     *        {@see Identity\TestIdentity::$pipelineId}. The two still answer different questions —
     *        *whose child is this* and *which test run is this part of* — and part company at every
     *        other level: a test's parent is its case, while its `pipelineId` is itself.
     */
    public function __construct(
        public ?int $parentId = null,
    ) {
        $this->runtimeId = RuntimeSequence::next();
    }

    /**
     * The address in the form the outside world writes it: what `--filter` accepts and what TeamCity
     * puts in a `locationHint`.
     *
     * This names *code*, so it carries neither the suite nor the type — the same class runs under any
     * suite, and both are filtered separately (`--suite`, `--type`). The result is meant to be parsed,
     * not read.
     *
     * Null where the level names no code at all: a case of free functions has no class to qualify,
     * and nothing else about it belongs in an FQN. The levels that always have one narrow the return
     * type back to `string`.
     *
     * @return non-empty-string|null
     */
    abstract public function fqn(): ?string;
}
