<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Acceptance;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Bridge\Symfony\Console\Command\Run;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Output\Html\HtmlPlugin;
use Testo\Test;
use Tests\Bridge\SymfonyConsole\Testo\Sandbox;

/**
 * Acceptance tests for the `--log-html` flag of `testo run`.
 *
 * Each test drives the real command end-to-end — Symfony parses the flags, {@see Run::initialize()}
 * normalizes them and a whole nested run happens in-process — inside a fresh {@see Sandbox} holding a
 * minimal config that points at a one-test stub suite. Assertions look at the exit code, the resolved
 * option and the files the run leaves on disk: the same surface a user observes after
 * `vendor/bin/testo --log-html`.
 *
 * The sandbox config swaps the default reporter for a fresh {@see HtmlPlugin::inert()} of its own: the
 * application defaults hold one shared instance per process, and its single-shot guard is already spent
 * by the outer run that executes these tests — without the swap a nested run could write no report no
 * matter what the flag says.
 */
#[Test]
#[Covers(Run::class)]
final class RunCommandTest
{
    private Sandbox $sandbox;

    public function bareLogHtmlWritesTheReportToTheDefaultLocation(): void
    {
        $tester = $this->run(['--log-html' => null]);

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'a valueless --log-html must be accepted, not rejected by the parser; output: ' . $tester->getDisplay(),
        );
        Assert::same(
            $tester->getInput()->getOption('log-html'),
            HtmlPlugin::DEFAULT_PATH,
            'a bare --log-html must fall back to the default report location',
        );
        Assert::true(
            \is_file($this->sandbox->path('runtime/report/index.html')),
            'a bare --log-html must write the report to runtime/report/index.html; output: ' . $tester->getDisplay(),
        );
    }

    public function logHtmlWithAnExplicitPathWritesThatSingleFile(): void
    {
        $tester = $this->run(['--log-html' => 'build/report.html']);

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'the stub suite must pass; output: ' . $tester->getDisplay(),
        );
        Assert::true(
            \is_file($this->sandbox->path('build/report.html')),
            'an explicit .html path must produce that single file, untouched by the bare-flag fallback; output: '
            . $tester->getDisplay(),
        );
        Assert::false(
            \is_dir($this->sandbox->path('runtime/report')),
            'the default location must stay untouched when a path is given',
        );
    }

    public function withoutTheFlagNothingIsResolvedAndNoReportIsWritten(): void
    {
        $tester = $this->run();

        Assert::same(
            $tester->getStatusCode(),
            Command::SUCCESS,
            'the stub suite must pass; output: ' . $tester->getDisplay(),
        );
        Assert::null(
            $tester->getInput()->getOption('log-html'),
            'an absent flag must stay absent — the fallback belongs to the bare flag only',
        );
        Assert::false(
            \is_dir($this->sandbox->path('runtime')),
            'a run without the flag must leave no report behind',
        );
    }

    #[BeforeTest]
    public function setUp(): void
    {
        $this->sandbox = Sandbox::create();
        $this->sandbox->writeFile('testo.php', self::configPointingAtTheStubSuite());
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->sandbox->destroy();
    }

    /**
     * The smallest config a nested run needs: no sources, one suite pointing at this module's stub case,
     * and a fresh inert reporter in place of the process-wide default (see the class docblock).
     * The stub directory is embedded as an absolute path — the sandbox CWD is elsewhere.
     */
    private static function configPointingAtTheStubSuite(): string
    {
        $stub = \var_export(\dirname(__DIR__) . '/Stub/Run', true);

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Testo\\Application\\Config\\ApplicationConfig;
            use Testo\\Application\\Config\\FinderConfig;
            use Testo\\Application\\Config\\Plugin\\ApplicationPlugins;
            use Testo\\Application\\Config\\SuiteConfig;
            use Testo\\Output\\Html\\HtmlPlugin;

            return new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        'Stub',
                        location: new FinderConfig(include: [{$stub}]),
                    ),
                ],
                plugins: ApplicationPlugins::without(HtmlPlugin::class)->with(HtmlPlugin::inert()),
            );
            PHP;
    }

    /**
     * Run the `run` command against the sandbox config in non-interactive mode.
     *
     * Pass CLI flags as the standard CommandTester input array; a bare flag is a key with a null value,
     * e.g. `['--log-html' => null]`.
     *
     * @param array<string, string|bool|null> $input
     */
    private function run(array $input = []): CommandTester
    {
        $tester = new CommandTester(new Run());
        $tester->execute(
            $input,
            ['interactive' => false, 'capture_stderr_separately' => false],
        );

        return $tester;
    }
}
