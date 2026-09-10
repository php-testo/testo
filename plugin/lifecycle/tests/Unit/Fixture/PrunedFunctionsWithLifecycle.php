<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Unit\Fixture;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;

/**
 * Call counters for the lifecycle functions below. Not autoloadable — the test
 * `require_once`s this file before touching the counters.
 */
final class PrunedFunctionsState
{
    public static int $beforeClassCalls = 0;
    public static int $afterClassCalls = 0;
    public static int $beforeTestCalls = 0;
    public static int $afterTestCalls = 0;
}

#[BeforeClass]
function prunedFnSetUpClass(): void
{
    ++PrunedFunctionsState::$beforeClassCalls;
}

#[AfterClass]
function prunedFnTearDownClass(): void
{
    ++PrunedFunctionsState::$afterClassCalls;
}

#[BeforeTest]
function prunedFnSetUp(): void
{
    ++PrunedFunctionsState::$beforeTestCalls;
}

#[AfterTest]
function prunedFnTearDown(): void
{
    ++PrunedFunctionsState::$afterTestCalls;
}

# The case's only test. Never referenced directly: the regression test empties the test set
# to simulate an outer case interceptor pruning it away.
function prunedFnTest(): void {}
