<?php

declare(strict_types=1);

namespace Testo\Application;

use Internal\Container\Container;
use Internal\Container\ObjectContainer;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\DefaultServicesConfig;
use Testo\Application\Config\Internal\ConfigInflector;
use Testo\Application\Config\RunConfiguration;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Application\Config\Plugin\PluginCollection;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Internal\Runner\SuiteRunner;
use Testo\Application\Internal\SuiteProvider;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Framework\WorkerFinished;
use Testo\Event\Framework\WorkerStarting;
use Testo\Filter;

final readonly class Application
{
    private function __construct(
        private ObjectContainer $container,
    ) {
        (new DefaultServicesConfig())->configure($container);
    }

    /**
     * Create the application instance with the provided configuration.
     */
    public static function createFromConfig(
        ApplicationConfig $config,
    ): self {
        $container = new ObjectContainer();
        $container->set($config);
        return new self($container);
    }

    /**
     * Create the application instance from ENV, CLI arguments, and config file.
     *
     * @param Path|null $configFile Path to config file
     * @param array<string, mixed> $inputOptions Command-line options
     * @param array<string, mixed> $inputArguments Command-line arguments
     * @param array<string, string> $environment Environment variables
     */
    public static function createFromInput(
        ?Path $configFile = null,
        array $inputOptions = [],
        array $inputArguments = [],
        array $environment = [],
    ): self {
        $container = new ObjectContainer();
        $args = [
            'env' => $environment,
            'inputArguments' => $inputArguments,
            'inputOptions' => $inputOptions,
        ];

        # What was asked of this run, for reporters that describe it. Environment stays out of it.
        $container->set(new RunConfiguration($configFile, $inputOptions, $inputArguments));

        # Bind reading provided config file
        $configFile === null or $container
            ->bind(ApplicationConfig::class, function () use ($configFile): ApplicationConfig {
                /** @psalm-suppress UnresolvableInclude */
                $cfg = include $configFile;
                $cfg instanceof ApplicationConfig or throw new \InvalidArgumentException(
                    \sprintf(
                        'Configuration file %s must return an instance of %s, %s returned.',
                        $configFile,
                        ApplicationConfig::class,
                        \get_debug_type($cfg),
                    ),
                );
                return $cfg;
            });

        # Register Config inflector
        $container->addInflector($container->make(ConfigInflector::class, $args));

        return new self($container);
    }

    public function run(): RunResult
    {
        return $this->container->scope(static function (Container $container): RunResult {
            $startedAt = \microtime(true);

            $appConfig = $container->get(ApplicationConfig::class);
            self::applyPlugins($container, self::resolvePlugins($appConfig->plugins, ApplicationPlugins::class));
            $filter = $container->get(Filter::class);

            $dispatcher = $container->get(EventDispatcherInterface::class);
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new WorkerStarting());

            $suiteProvider = $container->get(SuiteProvider::class);
            $status = Status::Passed;

            # Phase timers. Discovery and execution interleave — a suite is scanned, run, and only then
            # is the next one scanned — so these accumulate rather than mark intervals.
            $startup = \microtime(true) - $startedAt;
            $discovery = 0.0;
            $tests = 0.0;

            # Iterate and run Test Suites
            $suiteResults = [];
            foreach ($appConfig->suites as $config) {
                # Resolve a Test Suite from config and
                # run a separate container scope to isolate services and plugins.
                $suiteResult = $container->scope(
                    static function (Container $container) use (
                        $filter,
                        $config,
                        $suiteProvider,
                        &$discovery,
                        &$tests,
                    ): ?SuiteResult {
                        $at = \microtime(true);

                        # Apply plugins first to have all interceptors before suite files scanning
                        self::applyPlugins($container, self::resolvePlugins($config->plugins, SuitePlugins::class));

                        $suite = $suiteProvider->findSuite($config);
                        $discovery += \microtime(true) - $at;

                        if ($suite === null) {
                            return null;
                        }

                        $suiteRunner = $container->get(SuiteRunner::class);

                        $at = \microtime(true);
                        try {
                            return $suiteRunner->runSuite($suite, $filter);
                        } finally {
                            $tests += \microtime(true) - $at;
                        }
                    },
                );
                if ($suiteResult === null) {
                    continue;
                }

                $suiteResults[] = $suiteResult;
                $suiteResult->status->isFailure() and $status = Status::Failed;
            }

            $teardownAt = \microtime(true);
            $summary = Summary::combine(\array_map(
                static fn(SuiteResult $r): Summary => $r->summary,
                $suiteResults,
            ));

            # An empty run — no tests found, or every test filtered out — verified nothing, so it is
            # not a success. Flag it as Risky (unless something already failed) so every reporter and
            # the process exit code signal that the run was not normal.
            $status === Status::Passed && $summary->total() === 0 and $status = Status::Risky;

            $result = new RunResult(
                $suiteResults,
                status: $status,
                summary: $summary,
                timing: new RunTiming(
                    startup: $startup,
                    discovery: $discovery,
                    tests: $tests,
                    teardown: \microtime(true) - $teardownAt,
                ),
            );

            $dispatcher->dispatch(new WorkerFinished());
            $dispatcher->dispatch(new SessionFinished($result));
            return $result;
        });
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Normalize an iterable of plugins into a resolved flat list.
     *
     * @param iterable<array-key, PluginConfigurator> $plugins
     * @param class-string<ApplicationPlugins|SuitePlugins> $facade
     * @return list<PluginConfigurator>
     */
    private static function resolvePlugins(iterable $plugins, string $facade): array
    {
        return $plugins instanceof PluginCollection
            ? $plugins->toArray()
            : $facade::with(...$plugins)->toArray();
    }

    /**
     * Apply plugin services to the container.
     *
     * @param list<PluginConfigurator> $plugins
     */
    private static function applyPlugins(Container $container, array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $plugin->configure($container);
        }
    }
}
