<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

/**
 * The pipeline stage at which {@see FailingInterceptor} should throw.
 *
 * Each case reproduces one place where a user-authored interceptor can blow up:
 * before or after the `$next()` call, at either the test or the test-case level.
 */
enum FailStage: string
{
    /** Throw in {@see FailingInterceptor::runTest()} before calling `$next()`. */
    case TestBefore = 'test-before';

    /** Throw in {@see FailingInterceptor::runTest()} after `$next()` returned. */
    case TestAfter = 'test-after';

    /** Throw in {@see FailingInterceptor::runTestCase()} before calling `$next()`. */
    case CaseBefore = 'case-before';

    /** Throw in {@see FailingInterceptor::runTestCase()} after `$next()` returned. */
    case CaseAfter = 'case-after';
}
