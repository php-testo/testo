<?php

declare(strict_types=1);

namespace Tests\Output\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\PluginConfigurator;
use Testo\Output\Json\JsonPlugin;
use Testo\Output\Teamcity\TeamcityPlugin;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Output\Terminal\TerminalPlugin;
use Testo\Test;
use Testo\Testing\InjectPlugin;
use Tests\Output\Stub\Channels\CapturedConsolePlugin;
use Tests\Output\Stub\Channels\Run\ChannelCase;

/**
 * What a test writes to a channel reaches every renderer, and under that test. The renderers are wired the
 * way the `run` command wires them — as a late plugin of a real run — because a renderer fed events by
 * hand in a unit test never learns whether the run's messages are dispatched where it listens.
 */
#[Test]
final class ChannelStreamingTest
{
    #[Covers(TerminalPlugin::class)]
    public function theTerminalStreamsATestsChannelsBeforeItsResultLine(): void
    {
        $output = self::run(static fn(): TerminalPlugin => new TerminalPlugin());

        $at = static fn(string $needle): int => (int) \strpos($output, $needle);
        Assert::string($output)->contains('[stdout]')->contains('[' . ChannelCase::CHANNEL . ']');
        Assert::true($at('printed while passing') > 0, $output);
        Assert::true($at('logged while passing') > 0, $output);

        // Streamed, not recapped: the lines come out while the test runs, so they precede its result line.
        Assert::true($at('printed while passing') < $at('writesAndPasses'), $output);
        Assert::true($at('logged while passing') < $at('writesAndPasses'), $output);
    }

    #[Covers(TeamcityPlugin::class)]
    public function teamcityNestsATestsChannelsBetweenItsStartAndFinish(): void
    {
        $output = self::run(static fn(): TeamcityPlugin => new TeamcityPlugin());

        $started = \strpos($output, "##teamcity[testStarted name='writesAndPasses'");
        $finished = \strpos($output, "##teamcity[testFinished name='writesAndPasses'");
        Assert::true($started !== false && $finished !== false && $started < $finished, $output);
        $inside = \substr($output, $started, $finished - $started);

        // A `testStdOut` outside the test's own start/finish pair lands on no node in the IDE's run tree.
        Assert::string($inside)
            ->contains("##teamcity[testStdOut name='writesAndPasses' out='printed while passing|n' channel='stdout'")
            ->contains("##teamcity[testStdOut name='writesAndPasses' out='logged while passing|n' channel='" . ChannelCase::CHANNEL . "'");
    }

    #[Covers(JsonPlugin::class)]
    public function theJsonReportListsAFailedTestsChannelsInOrder(): void
    {
        $output = self::run(static fn($stdout): JsonPlugin => new JsonPlugin($stdout));

        /** @var array{failures: list<array{test: string, output?: list<array{channel: string, content: string}>}>} $report */
        $report = \json_decode($output, true, flags: \JSON_THROW_ON_ERROR);
        $failed = \array_values(\array_filter(
            $report['failures'],
            static fn(array $failure): bool => $failure['test'] === ChannelCase::class . '::writesAndFails',
        ));

        Assert::count($failed, 1);
        // Other plugins add channels of their own (the assertion history, say); the test's two lines have
        // to be there in the order they were written.
        $own = \array_values(\array_filter(
            $failed[0]['output'] ?? [],
            static fn(array $block): bool => \in_array($block['channel'], ['stdout', ChannelCase::CHANNEL], true),
        ));
        Assert::same($own, [
            ['channel' => 'stdout', 'content' => "printed while failing\n"],
            ['channel' => ChannelCase::CHANNEL, 'content' => "logged while failing\n"],
        ]);
    }

    /**
     * Runs the stub case with the renderer `$renderer` builds for the run's stdout stream, applied the way
     * the `run` command applies its stdout renderer, and returns what landed on that stream.
     *
     * @param \Closure(resource): PluginConfigurator $renderer
     */
    private static function run(\Closure $renderer): string
    {
        $stdout = \fopen('php://memory', 'w+');
        $stderr = \fopen('php://memory', 'w+');
        \assert($stdout !== false && $stderr !== false);

        # Configuring a renderer writes colorization into a process-global flag; put it back so the
        # outer run keeps its own colors.
        $colors = Style::areColorsEnabled();

        try {
            Application::createFromConfig(new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        'Output/Stub',
                        location: new FinderConfig(include: [\dirname(__DIR__) . '/Stub/Channels/Run']),
                        plugins: SuitePlugins::with(new InjectPlugin()),
                    ),
                ],
                plugins: ApplicationPlugins::with(new CapturedConsolePlugin($stdout, $stderr)),
            ))->run($renderer($stdout));

            \rewind($stdout);
            return (string) \stream_get_contents($stdout);
        } finally {
            Style::setColorsEnabled($colors);
            \fclose($stdout);
            \fclose($stderr);
        }
    }
}
