<?php

declare(strict_types=1);

namespace Testo\Pipeline\Middleware;

use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Pipeline\Interceptor;

/**
 * Intercept running a test suite.
 *
 * @extends Interceptor<CaseInfo, CaseResult>
 *
 * @api
 */
interface TestSuiteRunInterceptor extends Interceptor
{
    /**
     * @param SuiteInfo $info Test suite to run.
     * @param callable(CaseInfo): CaseResult $next Next interceptor or core logic to run the test suite.
     */
    public function runTestSuite(SuiteInfo $info, callable $next): SuiteResult;
}
