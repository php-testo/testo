<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\FullySkipped;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Testo\Skip;

/**
 * A fully skipped function-based case: every `#[Test]` function is under `#[Skip]`. Mirrors
 * {@see FullySkippedClassStub} for the function-based shape of the same scenario. Its two tests
 * spell the attribute both ways — `skippedFnOne` with a reason, `skippedFnTwo` without — so neither
 * form leaves the case with an active test.
 *
 * The skipped tests are flagged ahead of the run and stay active, so the
 * {@see \Testo\Lifecycle\Internal\LifecycleInterceptor} sees a case with tests but without a single
 * one to run: none of its hooks fire (the `#[Skip]` contract).
 *
 * Static hook counters accumulate across directory runs — feature tests assert deltas.
 * State is shared through {@see FullySkippedFunctionState} because functions have no `$this`.
 */
#[BeforeClass]
function skippedCaseSetUpClass(): void
{
    ++FullySkippedFunctionState::$beforeClassCalls;
}

#[AfterClass]
function skippedCaseTearDownClass(): void
{
    ++FullySkippedFunctionState::$afterClassCalls;
}

#[BeforeTest]
function skippedCaseSetUp(): void
{
    ++FullySkippedFunctionState::$beforeTestCalls;
}

#[AfterTest]
function skippedCaseTearDown(): void
{
    ++FullySkippedFunctionState::$afterTestCalls;
}

#[Test]
#[Skip('the whole functional case is skipped')]
function skippedFnOne(): void
{
    throw new \LogicException('Must never run: the test is skipped.');
}

#[Test]
#[Skip]
function skippedFnTwo(): void
{
    throw new \LogicException('Must never run: the test is skipped.');
}

/**
 * Call counters for the lifecycle functions above. Not autoloadable — the feature test
 * `require_once`s this file before touching the counters.
 */
final class FullySkippedFunctionState
{
    public static int $beforeClassCalls = 0;
    public static int $afterClassCalls = 0;
    public static int $beforeTestCalls = 0;
    public static int $afterTestCalls = 0;
}
