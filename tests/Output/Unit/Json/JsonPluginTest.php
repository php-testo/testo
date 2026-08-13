<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Json;

use Internal\Path;
use Internal\Container\ObjectContainer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Output\Json\JsonPlugin;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

#[Test]
#[Covers(JsonPlugin::class)]
final class JsonPluginTest
{
    public function writesReportToFileOnSessionFinished(): void
    {
        $path = self::tmpPath();
        try {
            self::dispatch(new JsonPlugin($path), self::failedRun());

            Assert::true(\file_exists($path));
            $report = self::decode((string) \file_get_contents($path));
            Assert::same($report['status'], 'failed');
            Assert::count($report['failures'], 1);
            Assert::same($report['failures'][0]['test'], SampleTestClass::class . '::failingTest');
        } finally {
            \is_file($path) and \unlink($path);
        }
    }

    public function createsMissingParentDirectories(): void
    {
        $dir = self::tmpDir() . '/testo_json_' . \uniqid();
        $path = $dir . '/nested/report.json';
        try {
            self::dispatch(new JsonPlugin($path), self::failedRun());

            Assert::true(\file_exists($path));
        } finally {
            \is_file($path) and \unlink($path);
            \is_dir($dir . '/nested') and \rmdir($dir . '/nested');
            \is_dir($dir) and \rmdir($dir);
        }
    }

    public function emptyOutputPathIsTreatedAsStdoutMode(): void
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        self::dispatch(new JsonPlugin('', $stream), self::failedRun());

        \rewind($stream);
        $written = (string) \stream_get_contents($stream);
        \fclose($stream);

        $report = self::decode($written);
        Assert::same($report['status'], 'failed');
    }

    public function fileModeAnnouncesTheFileAndStdoutModeAnnouncesNothing(): void
    {
        $path = self::tmpPath();
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $toFile = self::announcements(new JsonPlugin($path));
            $toStdout = self::announcements(new JsonPlugin(null, $stream));

            Assert::same(\array_map(
                static fn(object $event): string => $event::class,
                $toFile,
            ), [ReportFileGenerating::class, ReportFileGenerated::class]);
            Assert::same($toFile[0]->info->format, 'json');
            Assert::same((string) $toFile[1]->info->path, (string) Path::create($path));

            // Stdout mode has no artifact beside the output: the report *is* the output.
            Assert::same($toStdout, []);
        } finally {
            \fclose($stream);
            \is_file($path) and \unlink($path);
        }
    }

    /**
     * Runs the shortest session there is through the plugin and returns what it announced.
     *
     * @return list<ReportFileGenerating|ReportFileGenerated>
     */
    private static function announcements(JsonPlugin $plugin): array
    {
        $dispatcher = new EventDispatcher();
        $container = new ObjectContainer();
        $container->set($dispatcher, EventListenerCollector::class);
        $container->set($dispatcher, EventDispatcherInterface::class);
        $plugin->configure($container);

        $seen = [];
        $record = static function (ReportFileGenerating|ReportFileGenerated $event) use (&$seen): void {
            $seen[] = $event;
        };
        $dispatcher->addListener(ReportFileGenerating::class, $record);
        $dispatcher->addListener(ReportFileGenerated::class, $record);

        $dispatcher->dispatch(new SessionStarting());
        $dispatcher->dispatch(new SessionFinished(self::failedRun()));

        return $seen;
    }

    private static function dispatch(JsonPlugin $plugin, RunResult $result): void
    {
        $dispatcher = new EventDispatcher();
        $container = new ObjectContainer();
        $container->set($dispatcher, EventListenerCollector::class);
        $container->set($dispatcher, EventDispatcherInterface::class);
        $plugin->configure($container);

        $dispatcher->dispatch(new SessionFinished($result));
    }

    private static function failedRun(): RunResult
    {
        $info = new TestInfo(
            name: 'failingTest',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, 'failingTest')),
        );
        $test = new TestResult(info: $info, status: Status::Failed, failure: new \RuntimeException('boom'));
        $summary = new Summary(['Failed' => 1]);

        return new RunResult(
            [new SuiteResult([new CaseResult([$test], Status::Failed, $summary)], Status::Failed, $summary)],
            Status::Failed,
            0.0,
            $summary,
        );
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private static function decode(string $json): array
    {
        /** @var array<non-empty-string, mixed> */
        return \json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return non-empty-string
     */
    private static function tmpPath(): string
    {
        return self::tmpDir() . '/testo_json_plugin_' . \uniqid() . '.json';
    }

    /**
     * @return non-empty-string
     */
    private static function tmpDir(): string
    {
        $dir = \sys_get_temp_dir();
        \assert($dir !== '');

        return $dir;
    }
}
