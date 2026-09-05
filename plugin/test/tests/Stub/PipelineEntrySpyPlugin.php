<?php

declare(strict_types=1);

namespace Tests\Test\Stub;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\InterceptorCollector;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Records the address of every test that enters the per-test pipeline.
 *
 * Unlike a {@see \Testo\Event\Test\TestPipelineStarting} listener — which also sees the
 * events the skip interceptor dispatches for its synthetic results — a per-test interceptor
 * is reached only by tests that actually run through the pipeline. The record accumulates
 * across catalog runs — feature tests inspect the slice of their own run.
 */
final class PipelineEntrySpyPlugin implements PluginConfigurator
{
    /** @var list<non-empty-string> */
    public static array $entered = [];

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(
            new class implements TestRunInterceptor {
                #[\Override]
                public function runTest(TestInfo $info, callable $next): TestResult
                {
                    PipelineEntrySpyPlugin::$entered[] = $info->identity->fqn();

                    return $next($info);
                }
            },
        );
    }
}
