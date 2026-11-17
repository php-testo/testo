<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Runner;

use Internal\Container\Container;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\TestSuite\TestSuitesLocated;
use Testo\Event\TestSuite\TestSuiteStarting;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\SuiteLocatorInterceptor;
use Testo\Test;

/**
 * {@see TestSuitesLocated} announces every suite the run will go through before the first one is
 * scanned, including a suite that turns out to have no test cases and never starts.
 */
#[Test]
#[Covers(Application::class)]
final class SuitesLocatedTest
{
    public function locatedSuitesAreAnnouncedBeforeTheFirstSuiteStarts(): void
    {
        $events = [];
        $recorder = new class($events) implements PluginConfigurator {
            /**
             * @param list<string> $events
             */
            public function __construct(private array &$events) {}

            #[\Override]
            public function configure(Container $container): void
            {
                $listeners = $container->get(EventListenerCollector::class);
                $listeners->addListener(TestSuitesLocated::class, function (TestSuitesLocated $event): void {
                    $names = \array_map(static fn(SuiteConfig $suite): string => $suite->name, $event->suites);
                    $this->events[] = 'located: ' . \implode(', ', $names);
                });
                $listeners->addListener(TestSuiteStarting::class, function (TestSuiteStarting $event): void {
                    $this->events[] = 'starting: ' . $event->suiteInfo->name;
                });
            }
        };

        Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig('Empty', location: new FinderConfig(include: [__DIR__ . '/../../Stub/EmptyRun'])),
                new SuiteConfig('Runner', location: new FinderConfig(include: [__DIR__ . '/../../Stub/Runner'])),
            ],
        ))->run($recorder);

        Assert::same($events, ['located: Empty, Runner', 'starting: Runner']);
    }

    public function suitesFilteredIntoAKeyedArrayAreAnnouncedAsAList(): void
    {
        $located = null;
        $plugin = new class($located) implements PluginConfigurator {
            public function __construct(private ?array &$located) {}

            #[\Override]
            public function configure(Container $container): void
            {
                $container->get(InterceptorProvider::class)->addInterceptor(new class implements SuiteLocatorInterceptor {
                    #[\Override]
                    public function locateTestSuites(ApplicationConfig $config, callable $next): array
                    {
                        // Keeps the key of the surviving suite, as `array_filter()` does.
                        return \array_filter($next($config), static fn(SuiteConfig $suite): bool => $suite->name === 'Runner');
                    }
                });
                $container->get(EventListenerCollector::class)->addListener(
                    TestSuitesLocated::class,
                    function (TestSuitesLocated $event): void {
                        $this->located = $event->suites;
                    },
                );
            }
        };

        Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig('Empty', location: new FinderConfig(include: [__DIR__ . '/../../Stub/EmptyRun'])),
                new SuiteConfig('Runner', location: new FinderConfig(include: [__DIR__ . '/../../Stub/Runner'])),
            ],
        ))->run($plugin);

        Assert::array($located)->isList()->hasCount(1);
    }
}
