<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\FullyParked;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Testo\Test\Skip;

/**
 * A fully parked function-based case: every `#[Test]` function is under `#[Skip]`.
 *
 * The `#[Skip]` case interceptor removes the parked tests from the case's test set before the
 * {@see \Testo\Lifecycle\Internal\LifecycleInterceptor} runs, so hook discovery must not depend
 * on the surviving tests: `#[BeforeClass]`/`#[AfterClass]` still run for the case (the `#[Skip]`
 * contract), while the per-test hooks have nothing to wrap.
 *
 * Static hook counters accumulate across catalog runs — feature tests assert deltas.
 * State is shared through {@see FullyParkedFunctionState} because functions have no `$this`.
 */
#[BeforeClass]
function parkedCaseSetUpClass(): void
{
    ++FullyParkedFunctionState::$beforeClassCalls;
}

#[AfterClass]
function parkedCaseTearDownClass(): void
{
    ++FullyParkedFunctionState::$afterClassCalls;
}

#[BeforeTest]
function parkedCaseSetUp(): void
{
    ++FullyParkedFunctionState::$beforeTestCalls;
}

#[AfterTest]
function parkedCaseTearDown(): void
{
    ++FullyParkedFunctionState::$afterTestCalls;
}

#[Test]
#[Skip('the whole functional case is parked')]
function parkedFnOne(): void
{
    throw new \LogicException('Must never run: the test is parked.');
}

#[Test]
#[Skip]
function parkedFnTwo(): void
{
    throw new \LogicException('Must never run: the test is parked.');
}

final class FullyParkedFunctionState
{
    public static int $beforeClassCalls = 0;
    public static int $afterClassCalls = 0;
    public static int $beforeTestCalls = 0;
    public static int $afterTestCalls = 0;
}
