<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Html;

use Internal\Container\ObjectContainer;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\RunConfiguration;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\RunResult;
use Testo\Core\Value\Status;
use Testo\Core\Report\ReportInfo;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Output\Html\HtmlPlugin;
use Testo\Output\Html\Internal\ReportInput;
use Testo\Test;

/**
 * How a destination is resolved when both `testo.php` and the command line have something to say.
 */
#[Test]
#[Covers(HtmlPlugin::class)]
final class HtmlPluginTest
{
    public function flagsActivateAnInertReporter(): void
    {
        self::inDirectory(static function (string $directory): void {
            $input = new ReportInput();
            $input->htmlPath = $directory . '/flag.html';
            $input->dataPath = $directory . '/flag.json';

            self::run(HtmlPlugin::inert(), $input);

            Assert::true(\is_file($directory . '/flag.html'));
            Assert::true(\is_file($directory . '/flag.json'));
        });
    }

    public function pathsGivenInCodeIgnoreTheFlags(): void
    {
        self::inDirectory(static function (string $directory): void {
            $input = new ReportInput();
            $input->dataPath = $directory . '/flag.json';

            self::run(new HtmlPlugin($directory . '/configured.html'), $input);

            // The flag left the second slot alone: the inert default in the application plugins is the one
            // that serves it, and filling it here would write the same file twice and announce it twice.
            Assert::true(\is_file($directory . '/configured.html'));
            Assert::false(\is_file($directory . '/flag.json'));
        });
    }

    public function anInertReporterWithNoFlagsAttachesNothing(): void
    {
        self::inDirectory(static function (string $directory): void {
            $dispatcher = self::run(HtmlPlugin::inert(), new ReportInput());

            Assert::same(\array_diff((array) \scandir($directory), ['.', '..']), []);
            // Nothing was attached either — an inert reporter costs a run nothing at all.
            Assert::same($dispatcher->getListenersForEvent(new SessionStarting())->current(), null);
        });
    }

    public function aPathNamedByBothAConfiguredPluginAndAFlagIsWrittenOnce(): void
    {
        self::inDirectory(static function (string $directory): void {
            $input = new ReportInput();
            $input->htmlPath = $directory . '/report';   // the flag names the configured path

            $generated = self::generatedBy(
                $input,
                new HtmlPlugin($directory . '/report'),   // configured
                HtmlPlugin::inert(),                       // serves the flag
            );

            // Two instances, one sink: the same destination is deduplicated, so the file is written and
            // announced once rather than twice with a second, identical IDE button.
            Assert::count($generated, 1);
            Assert::same($generated[0]->format, 'html');
            Assert::true(\is_file($directory . '/report/index.html'));
        });
    }

    public function distinctDestinationsAcrossInstancesAreAllServedFromOneRun(): void
    {
        self::inDirectory(static function (string $directory): void {
            $input = new ReportInput();
            $input->dataPath = $directory . '/flag.json';

            $generated = self::generatedBy(
                $input,
                new HtmlPlugin($directory . '/report.html'),   // configured page
                HtmlPlugin::inert(),                            // flag document
            );

            // Both land, each announced under its own format — the configured page and the flag's document.
            Assert::same(\array_map(static fn(ReportInfo $i): string => $i->format, $generated), ['html', 'testo-report']);
            Assert::true(\is_file($directory . '/report.html'));
            Assert::true(\is_file($directory . '/flag.json'));
        });
    }

    /**
     * Configures the reporter against a container holding the given CLI input, then plays the shortest run
     * there is: a session that starts and finishes with no tests in it.
     */
    private static function run(HtmlPlugin $plugin, ReportInput $input): EventDispatcher
    {
        $dispatcher = new EventDispatcher();

        $container = new ObjectContainer();
        $container->set($dispatcher, EventListenerCollector::class);
        $container->set($dispatcher, EventDispatcherInterface::class);
        $container->set($input, ReportInput::class);
        $container->set(new RunConfiguration(), RunConfiguration::class);
        $container->set(new ApplicationConfig(), ApplicationConfig::class);

        $plugin->configure($container);

        $dispatcher->dispatch(new SessionStarting());
        $dispatcher->dispatch(new SessionFinished(new RunResult([], Status::Passed)));

        return $dispatcher;
    }

    /**
     * Configures several plugins against one container — as the application does when the inert default
     * and a configured instance coexist — plays the shortest run, and returns every report announced as
     * written, in the order the run stated them.
     *
     * @return list<ReportInfo>
     */
    private static function generatedBy(ReportInput $input, HtmlPlugin ...$plugins): array
    {
        $dispatcher = new EventDispatcher();

        $container = new ObjectContainer();
        $container->set($dispatcher, EventListenerCollector::class);
        $container->set($dispatcher, EventDispatcherInterface::class);
        $container->set($input, ReportInput::class);
        $container->set(new RunConfiguration(), RunConfiguration::class);
        $container->set(new ApplicationConfig(), ApplicationConfig::class);

        $generated = [];
        $dispatcher->addListener(
            ReportFileGenerated::class,
            static function (ReportFileGenerated $event) use (&$generated): void {
                $generated[] = $event->info;
            },
        );

        foreach ($plugins as $plugin) {
            $plugin->configure($container);
        }

        $dispatcher->dispatch(new SessionStarting());
        $dispatcher->dispatch(new SessionFinished(new RunResult([], Status::Passed)));

        return $generated;
    }

    /**
     * @param \Closure(string): void $assertions
     */
    private static function inDirectory(\Closure $assertions): void
    {
        $directory = \sys_get_temp_dir() . '/testo-html-plugin-' . \bin2hex(\random_bytes(6));
        \mkdir($directory, 0o777, true);

        try {
            $assertions($directory);
        } finally {
            self::remove(Path::create($directory));
        }
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
