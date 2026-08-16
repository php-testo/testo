<?php

declare(strict_types=1);

namespace Tests\Output\Feature\Html;

use Internal\Path;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Output\Html\HtmlPlugin;
use Testo\Test;
use Tests\Output\Stub\Html\ReportSpyPlugin;

/**
 * A whole run through the reporter: what lands on disk, in which layout, and what the run tells the rest
 * of the world about it.
 *
 * The hard constraint is that the report opens over `file://` with no server, which forbids more than
 * fetching — XHR on local files, ES modules, dynamic `import()` and workers are all unavailable because
 * the origin is `null`. That is a property of the written files, so it is asserted on them.
 */
#[Test]
#[Covers(HtmlPlugin::class)]
final class ReportGenerationTest
{
    public function aDirectoryReportIsWrittenWithRelativeAssetsAndAnnouncedByItsEntryFile(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => new HtmlPlugin($path . '/report'),
            static function (string $directory, array $reports): void {
                $entry = $directory . '/report/index.html';
                Assert::true(\is_file($entry), $entry);
                Assert::true(\is_file($directory . '/report/assets/report.css'));
                Assert::true(\is_file($directory . '/report/assets/report.js'));
                Assert::true(\is_file($directory . '/report/assets/data.js'));

                $html = (string) \file_get_contents($entry);

                // Relative paths and classic scripts: an absolute path or a module would leave the report
                // working only on the machine that wrote it, or not at all.
                Assert::string($html)->contains('href="assets/report.css"');
                Assert::string($html)->contains('<script src="assets/data.js"></script>');
                Assert::string($html)->notContains('type="module"');
                Assert::string($html)->notContains('http://');
                Assert::string($html)->notContains('https://');

                // The data is a script assigning a global, because fetching a local file is exactly what a
                // null origin forbids.
                Assert::string((string) \file_get_contents($directory . '/report/assets/data.js'))
                    ->contains('window.TESTO_REPORT = {');

                // The announcement points at the entry file, never at the directory, so a consumer opens
                // it without having to know the layout.
                Assert::same(\count($reports), 1);
                Assert::same($reports[0]->info->format, 'html');
                Assert::string((string) $reports[0]->info->path)->contains('report/index.html');
            },
        );
    }

    public function anHtmlDestinationInlinesEverythingIntoOneFile(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => new HtmlPlugin($path . '/single.html'),
            static function (string $directory, array $reports): void {
                $file = $directory . '/single.html';
                Assert::true(\is_file($file));
                Assert::false(\is_dir($directory . '/assets'));

                $html = (string) \file_get_contents($file);

                // Nothing left to load means none of the file:// restrictions apply at all.
                Assert::string($html)->contains('window.TESTO_REPORT = {');
                Assert::string($html)->contains('<style>');
                Assert::string($html)->notContains('src="assets/');
                Assert::string($html)->notContains('href="assets/');

                Assert::same(\count($reports), 1);
                Assert::string((string) $reports[0]->info->path)->contains('single.html');
            },
        );
    }

    public function theDocumentIsAlsoWrittenOnItsOwn(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => new HtmlPlugin(null, $path . '/report.json'),
            static function (string $directory, array $reports): void {
                $file = $directory . '/report.json';
                Assert::true(\is_file($file));

                $document = self::read($file);

                // The report is a view over the data; the data is an artifact in its own right, so it is
                // announced as its own format rather than as a side effect of the page.
                Assert::same($document['schemaVersion'], 1);
                Assert::same($document['run']['status'], 'passed');
                Assert::same($document['run']['summary']['total'], 1);
                Assert::same(\count($reports), 1);
                // Its own id, not a bare `json`: the run summary `--log-json` writes is JSON too, and a
                // consumer switching on the format has to be able to tell the two schemas apart.
                Assert::same($reports[0]->info->format, 'testo-report');
            },
        );
    }

    public function theRunItDescribesIsTheOneThatHappened(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => new HtmlPlugin(null, $path . '/report.json'),
            static function (string $directory, array $reports): void {
                $document = self::read($directory . '/report.json');
                $test = $document['suites'][0]['cases'][0]['tests'][0];

                Assert::same($test['name'], 'itPrintsAndPasses');
                Assert::same($test['status'], 'passed');
                Assert::same($test['metrics']['assertions'], 1);
                // Phases are measured by the application, not guessed by the reporter.
                Assert::same(\array_column($document['run']['phases'], 'name'), [
                    'startup', 'discovery', 'tests', 'teardown',
                ]);
                // The test began somewhere inside the run — what a timeline needs and a result cannot say.
                Assert::true($test['startedAt'] >= 0.0);
                Assert::string((string) \json_encode($test['messages']))->contains('from the test');
            },
        );
    }

    public function aReportIsAnnouncedWhenTheRunStartsAndAgainOnceItIsWritten(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => new HtmlPlugin($path . '/report'),
            static function (string $directory, array $reports): void {
                Assert::same(ReportSpyPlugin::sequence(), [
                    ReportFileGenerating::class,
                    ReportFileGenerated::class,
                ]);

                // The first announcement lands while the run's output is still open — no suite has closed
                // yet — because a `testoReport` service message after the last `testSuiteFinished` has no
                // node in an IDE's run tree to attach to.
                Assert::same(ReportSpyPlugin::$seen[0]['suitesFinished'], 0);
                Assert::same(ReportSpyPlugin::$seen[1]['suitesFinished'], 1);

                // A promise first, a fact second: nothing is readable at the announced path until the run
                // is over, so only the later event means the report can be opened.
                Assert::false(ReportSpyPlugin::$seen[0]['existed']);
                Assert::true(ReportSpyPlugin::$seen[1]['existed']);

                // Both name the same entry file, so a consumer of the early one has nothing to correct.
                Assert::same(
                    (string) ReportSpyPlugin::$seen[0]['event']->info->path,
                    (string) ReportSpyPlugin::$seen[1]['event']->info->path,
                );
                Assert::string((string) $reports[0]->info->path)->contains('report/index.html');
            },
        );
    }

    public function anInertReporterWritesNothing(): void
    {
        self::inReport(
            static fn(string $path): HtmlPlugin => HtmlPlugin::inert(),
            static function (string $directory, array $reports): void {
                // The application defaults hold an inert copy so the CLI flags have something to activate;
                // a project that passes no flag must end up with no files.
                Assert::same(\array_diff((array) \scandir($directory), ['.', '..']), []);
                Assert::same($reports, []);
            },
        );
    }

    /**
     * Runs one stub suite with the reporter the factory builds for a fresh temporary directory, hands the
     * directory and everything the run announced to the assertions, and clears the directory afterwards.
     *
     * @param \Closure(string): HtmlPlugin $reporter
     * @param \Closure(string, list<ReportFileGenerated>): void $assertions
     */
    private static function inReport(\Closure $reporter, \Closure $assertions): void
    {
        $directory = \sys_get_temp_dir() . '/testo-html-report-' . \bin2hex(\random_bytes(6));
        \mkdir($directory, 0o777, true);

        ReportSpyPlugin::reset();

        try {
            Application::createFromConfig(new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        'Output/Stub',
                        location: new FinderConfig(include: [\dirname(__DIR__, 2) . '/Stub/Html/Run']),
                    ),
                ],
                plugins: ApplicationPlugins::without(HtmlPlugin::class)->with(
                    $reporter($directory),
                    new ReportSpyPlugin(),
                ),
            ))->run();

            $assertions($directory, ReportSpyPlugin::generated());
        } finally {
            self::remove(Path::create($directory));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $file): array
    {
        /** @var array<string, mixed> */
        return \json_decode((string) \file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
    }

    private static function remove(Path $path): void
    {
        $target = (string) $path;

        if (\is_dir($target)) {
            foreach (\array_diff((array) \scandir($target), ['.', '..']) as $entry) {
                self::remove($path->join((string) $entry));
            }
            \rmdir($target);
            return;
        }

        \is_file($target) and \unlink($target);
    }
}
