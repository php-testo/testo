<?php

declare(strict_types=1);

namespace Testo\Spec;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Pipeline\InterceptorCollector;
use Testo\Spec\Internal\SpecCaseOrderInterceptor;
use Testo\Spec\Internal\SpecCollector;
use Testo\Spec\Internal\SpecInput;
use Testo\Spec\Internal\SpecSuiteOrderInterceptor;

/**
 * Wires the spec-driven workflow: ordering test execution by spec number and generating the document.
 *
 * The {@see \Testo\Spec} attribute publishes fragments to the messenger channel on its own, with no
 * plugin required. This plugin adds the two optional halves:
 *
 * - **Reordering** (`reorder`, on by default): Test Cases and the tests within them run in spec-number
 *   order — class-level {@see \Testo\Spec\SpecHeader} numbers sort the cases, method numbers sort the tests.
 * - **Generation** (off by default; enabled by `collect` or the `--spec` / `--spec-dir` CLI flags):
 *   a {@see SpecCollector} gathers every channel fragment and renders one ordered Markdown document
 *   when the session ends.
 *
 * @api
 */
final readonly class SpecPlugin implements PluginConfigurator
{
    /**
     * @param non-empty-string $outputDir Directory the generated document is written to.
     * @param bool $collect Generate the document without needing a CLI flag.
     * @param bool $reorder Reorder Test Cases and tests by their spec number before they run.
     */
    public function __construct(
        private string $outputDir = 'specs',
        private bool $collect = false,
        private bool $reorder = true,
    ) {}

    #[\Override]
    public function configure(Container $container): void
    {
        $this->reorder and $this->configureReordering($container);

        $input = $container->get(SpecInput::class);
        ($this->collect || $input->isEnabled()) and $this->configureGeneration($container, $input);
    }

    private function configureReordering(Container $container): void
    {
        $interceptors = $container->get(InterceptorCollector::class);
        $interceptors->addInterceptor(new SpecSuiteOrderInterceptor());
        $interceptors->addInterceptor(new SpecCaseOrderInterceptor());
    }

    private function configureGeneration(Container $container, SpecInput $input): void
    {
        // Idempotent across suites: a single session-scoped collector owns the whole run tree.
        if ($container->has(SpecCollector::class)) {
            return;
        }

        $collector = new SpecCollector($input->resolveDir() ?? $this->outputDir);
        $container->set($collector);

        $container->get(EventListenerCollector::class)
            ->addListener(TestSuiteFinished::class, $collector->onTestSuiteFinished(...));
    }
}
