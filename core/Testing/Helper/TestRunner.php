<?php

declare(strict_types=1);

namespace Testo\Testing\Traits;

use InvalidArgumentException as InvalidArgument;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Common\PluginConfigurator;
use Testo\Common\Reflection;
use Testo\Core\Context\TestResult;
use Testo\Testing\Attribute\TestingSuite;

/**
 * @internal
 * @psalm-internal Testo
 */
final class TestRunner
{
    /**
     * Run a specific test function and return its result.
     *
     * @param array|callable-string $testFunction The test function to run,
     *        either as a callable array to a method or as a fully qualified function name.
     * @return TestResult The result of the specified test function.
     * @throws \RuntimeException If the test function is not found.
     */
    public static function runTest(array|string $testFunction): TestResult
    {
        $isFunction = self::isFunction($testFunction);

        # Run application to get test results
        $suites = self::getTestoApp()->run()->results;

        # Find and return the test result for the given test function
        foreach ($suites as $suite) {
            foreach ($suite->results as $case) {
                foreach ($case->results as $test) {
                    $reflection = $test->info->testDefinition->reflection;
                    if ($isFunction) {
                        if ($reflection instanceof \ReflectionFunction && $reflection->getName() === $testFunction) {
                            return $test;
                        }

                        continue;
                    }

                    if ($reflection instanceof \ReflectionMethod && $reflection->getShortName() === $testFunction[1]) {
                        if ($reflection->getDeclaringClass()->getName() === $testFunction[0]) {
                            return $test;
                        }
                    }
                }
            }
        }

        throw new InvalidArgument('Test function not found: ' . $testFunction());
    }

    protected static function getTestoApp(): Application
    {
        /**
         * Try to create Application instance from Call Stack Attributes
         * @var list<\ReflectionAttribute<TestingSuite>> $suiteConfigs
         */
        $suiteConfigs = Reflection::getAttributesFromCallStack(
            attributeClass: TestingSuite::class,
            includeClasses: true,
            limit: 1,
        );

        $suiteConfigs === [] and throw new \RuntimeException('Testing Suite is not configured.');
        $config = \reset($suiteConfigs)->newInstance();

        # Extra plugins requested by the test, on top of the suite defaults.
        $plugins = \array_map(
            static fn(string|PluginConfigurator $p): PluginConfigurator => \is_string($p) ? new $p() : $p,
            $config->plugins,
        );

        # The application-level plugins stay at their defaults below, so a default plugin re-listed
        # by the test would be configured twice (once at application scope, once at suite scope).
        # Drop those duplicates and keep only the genuinely extra ones.
        $defaultClasses = \array_map(
            static fn(PluginConfigurator $p): string => $p::class,
            ApplicationPlugins::defaults()->toArray(),
        );
        $plugins = \array_values(\array_filter(
            $plugins,
            static fn(PluginConfigurator $p): bool => !\in_array($p::class, $defaultClasses, true),
        ));

        return Application::createFromConfig(
            new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        'Testing',
                        location: new FinderConfig(
                            include: [$config->path],
                        ),
                        plugins: $plugins,
                    ),
                ],
            ),
        );
    }

    /**
     * Determine if the provided test function is a valid function or method.
     *
     * @param array|non-empty-string $testFunction The test function to validate.
     * @return bool True if the test function is a valid function, false if it's a method.
     * @throws InvalidArgument If the test function is neither a valid function nor a method.
     *
     * @psalm-assert-if-false callable $testFunction
     * @psalm-assert-if-true callable-string $testFunction
     */
    private static function isFunction(array|string $testFunction): bool
    {
        $isFunction = match (true) {
            \is_array($testFunction) => false,
            \function_exists($testFunction) => true,
            default => throw new InvalidArgument('Invalid test function provided.'),
        };
        $isFunction or \method_exists($testFunction[0], $testFunction[1]) or throw new InvalidArgument(
            'Invalid test method provided.',
        );
        return $isFunction;
    }
}
