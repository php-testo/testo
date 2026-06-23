<?php

declare(strict_types=1);

namespace Tests\Spec\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\RunResult;
use Testo\Spec;
use Testo\Spec\Internal\SpecCaseOrderInterceptor;
use Testo\Spec\Internal\SpecCollector;
use Testo\Spec\Internal\SpecSuiteOrderInterceptor;
use Testo\Spec\SpecHeader;
use Testo\Spec\SpecPlugin;
use Testo\Test;

/**
 * End-to-end, Spec-Driven feature tests: a real {@see Application} runs the stub suite with the
 * plugin, and we assert on the generated document and the execution order. Each test documents the
 * behaviour it proves via `#[Spec]`, under its own section.
 */
#[Test]
#[Covers(SpecPlugin::class)]
#[Covers(SpecCollector::class)]
#[Covers(SpecSuiteOrderInterceptor::class)]
#[Covers(SpecCaseOrderInterceptor::class)]
#[SpecHeader(title: 'Generating the document', number: '2')]
final class SpecDocumentFeatureTest
{
    #[Spec(story: <<<'MD'
        Running a suite with the plugin renders a single `spec.md`: numbered sections come first in
        number order (`# 2. Authentication` before `# 5. Checkout`) and items are auto-numbered
        `{section}.{n}`.
        MD)]
    #[SpecHeader(title: 'An ordered, numbered document')]
    public function generatesAnOrderedNumberedDocument(): void
    {
        $dir = __DIR__ . '/../runtime/feature-doc';
        foreach ((array) \glob($dir . '/*.md') as $file) {
            \is_string($file) and @\unlink($file);
        }

        self::run(plugins: [new SpecPlugin()], dir: $dir);

        $content = (string) \file_get_contents($dir . '/spec.md');
        Assert::true(\str_contains($content, '# 2. Authentication'));
        Assert::true(\str_contains($content, '# 5. Checkout'));
        Assert::true(\str_contains($content, '## 2.1 login'));
        Assert::true(\strpos($content, '# 2. Authentication') < \strpos($content, '# 5. Checkout'));
    }

    #[Spec(story: <<<'MD'
        Fragments without a section number are gathered into a trailing `# Uncategorized` block instead
        of being dropped.
        MD)]
    #[SpecHeader(title: 'Unnumbered specs go to the tail')]
    public function gathersUnnumberedSpecsAtTheEnd(): void
    {
        $dir = __DIR__ . '/../runtime/feature-tail';
        foreach ((array) \glob($dir . '/*.md') as $file) {
            \is_string($file) and @\unlink($file);
        }

        self::run(plugins: [new SpecPlugin()], dir: $dir);

        $content = (string) \file_get_contents($dir . '/spec.md');
        Assert::true(\str_contains($content, '# Uncategorized'));
        Assert::true(\strpos($content, '# 5. Checkout') < \strpos($content, '# Uncategorized'));
    }

    #[Spec(story: <<<'MD'
        With reordering on, Test Cases run in section-number order and cases without a number run last,
        regardless of discovery order.
        MD)]
    #[SpecHeader(title: 'Execution follows the numbers')]
    public function reordersExecutionToMatchTheNumbers(): void
    {
        $order = self::caseOrder(self::run(plugins: [new SpecPlugin()]));

        Assert::same($order, ['AuthStub [test]', 'SpecStub [test]', 'MiscStub [test]']);
    }

    #[Spec(story: 'With `reorder: false` the cases keep the order they would have without the plugin.')]
    #[SpecHeader(title: 'Reordering can be turned off')]
    public function keepsDiscoveredOrderWhenReorderingIsOff(): void
    {
        $off = self::caseOrder(self::run(plugins: [new SpecPlugin(reorder: false)]));
        $baseline = self::caseOrder(self::run(plugins: []));

        Assert::same($off, $baseline);
    }

    /**
     * @param list<\Testo\Common\PluginConfigurator> $plugins
     */
    private static function run(array $plugins, ?string $dir = null): RunResult
    {
        $app = Application::createFromInput(
            inputOptions: $dir === null ? [] : ['spec-dir' => $dir],
        );
        $app->getContainer()->set(
            new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        'SpecStubs',
                        location: new FinderConfig(include: [__DIR__ . '/../Stub']),
                    ),
                ],
                plugins: $plugins,
            ),
            ApplicationConfig::class,
        );

        return $app->run();
    }

    /**
     * Distinct Test Case names in execution order.
     *
     * @return list<non-empty-string>
     */
    private static function caseOrder(RunResult $result): array
    {
        $order = [];
        foreach ($result->results as $suite) {
            foreach ($suite->results as $case) {
                foreach ($case->results as $test) {
                    $name = $test->info->caseInfo->name;
                    \in_array($name, $order, true) or $order[] = $name;
                }
            }
        }

        return $order;
    }
}
