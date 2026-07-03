<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * A deliberately misbehaving user-style interceptor: it throws a plain {@see \RuntimeException}
 * at the {@see FailStage} declared by the {@see FailPipeline} attribute it was attached for.
 *
 * The interceptor is wired to the attribute via {@see \Testo\Pipeline\Attribute\FallbackInterceptor}
 * on {@see FailPipeline}, so {@see \Testo\Pipeline\Internal\AttributesInterceptor} attaches it (and
 * injects the attribute instance here) only for marked tests/cases — no plugin needed. Method-level
 * marks reach {@see self::runTest()}; class-level marks reach both, but each method reacts only to
 * its own stages.
 *
 * The point is to observe how a throw from inside the pipeline (as opposed to a throw from the test
 * body) is reflected in the resulting statuses and the final report.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST)]
final readonly class FailingInterceptor implements TestRunInterceptor, TestCaseRunInterceptor
{
    public const MESSAGE = 'FailingInterceptor exploded';

    public function __construct(
        private FailPipeline $attribute,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $this->attribute->stage === FailStage::TestBefore and throw new \RuntimeException($this->message());

        $result = $next($info);

        $this->attribute->stage === FailStage::TestAfter and throw new \RuntimeException($this->message());

        return $result;
    }

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $this->attribute->stage === FailStage::CaseBefore and throw new \RuntimeException($this->message());

        $result = $next($info);

        $this->attribute->stage === FailStage::CaseAfter and throw new \RuntimeException($this->message());

        return $result;
    }

    private function message(): string
    {
        return self::MESSAGE . ': ' . $this->attribute->stage->value;
    }
}
