<?php

declare(strict_types=1);

namespace Testo\Pipeline\Middleware;

use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Pipeline\Interceptor;

/**
 * Intercept running a test suite.
 *
 * @extends Interceptor<SuiteInfo, SuiteResult>
 *
 * @api
 */
interface TestSuiteRunInterceptor extends Interceptor
{
    /**
     * @param SuiteInfo $info Test suite to run.
     * @param callable(SuiteInfo): SuiteResult $next Next interceptor or core logic to run the test suite.
     */
    public function runTestSuite(SuiteInfo $info, callable $next): SuiteResult;
}
