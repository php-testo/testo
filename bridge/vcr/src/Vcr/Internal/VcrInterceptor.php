<?php

declare(strict_types=1);

namespace Testo\Bridge\Vcr\Internal;

use Testo\Bridge\VCR;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use VCR\VCR as PhpVcr;

/**
 * Inserts the cassette named by a test's (or its case's) {@see VCR} attribute for the duration of the
 * test, then ejects it. Tests without the attribute pass straight through.
 *
 * Runs innermost so the cassette is active as close as possible to the test body.
 *
 * NOTE (concurrency): PHP-VCR is process-global static state — one active cassette per process, plus
 * a global library-hook install. Under Testo's fiber-based concurrency, sibling tests would clobber
 * each other's cassette. The starter implementation does not yet isolate this; the intended approach
 * (drive the test inside its own fiber so its VCR window never leaks across a suspension, à la the
 * Mockery bridge's container guard) is described in SPEC.md and tracked as a TODO.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Vcr
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    testType: TestType::Test,
)]
final readonly class VcrInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $cassette = self::resolveCassette($info);
        if ($cassette === null) {
            return $next($info);
        }

        PhpVcr::turnOn();
        PhpVcr::insertCassette($cassette->name);
        try {
            return $next($info);
        } finally {
            PhpVcr::eject();
            PhpVcr::turnOff();
        }
    }

    /**
     * Resolve the effective cassette for the test: the method-level {@see VCR} attribute wins,
     * otherwise the class-level one, otherwise none.
     */
    private static function resolveCassette(TestInfo $info): ?VCR
    {
        $method = $info->testDefinition->reflection->getAttributes(VCR::class);
        if ($method !== []) {
            return $method[0]->newInstance();
        }

        $class = $info->caseInfo->definition->reflection?->getAttributes(VCR::class) ?? [];
        return $class === [] ? null : $class[0]->newInstance();
    }
}
