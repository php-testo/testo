<?php

declare(strict_types=1);

namespace Tests\Assert;

use InvalidArgumentException as InvalidArgument;
use Testo\Application;
use Testo\Common\Filter;
use Testo\Config\ApplicationConfig;
use Testo\Config\FinderConfig;
use Testo\Config\SuiteConfig;
use Testo\Render\StdoutRenderer;
use Testo\Render\TerminalInterceptor;
use Testo\Test\Dto\TestResult;

final class StubRunner
{
    /**
     * Run a specific test function and return its result.
     *
     * @param array&callable|callable-string $testFunction The test function to run,
     *        either as a callable array to a method or as a fully qualified function name.
     * @return TestResult The result of the specified test function.
     * @throws \RuntimeException If the test function is not found.
     */
    public static function runTest(array|string $testFunction): TestResult
    {
        $isFunction = self::isFunction($testFunction);

        # todo Configure Filter to run only the given test function
        $filter = new Filter();

        # Run application to get test results
        $suites = self::app()->run($filter)->results;

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

    private static function app(): Application
    {
        # Create and cache application instance
        static $app = null;
        if ($app !== null) {
            return $app;
        }

        $app = Application::createFromConfig(
            new ApplicationConfig(
                src: null,
                suites: [
                    new SuiteConfig(
                        'Stubs',
                        location: new FinderConfig(
                            include: [__DIR__ . '/Stub'],
                        ),
                    ),
                ],
            ),
        );
        return $app;
    }

    /**
     * Determine if the provided test function is a valid function or method.
     * @param array&callable|non-empty-string $testFunction The test function to validate.
     * @psalm-assert-if-false array&callable $testFunction
     * @psalm-assert-if-true callable-string $testFunction
     */
    private static function isFunction(array|string $testFunction): bool
    {
        $isFunction = match (true) {
            \is_array($testFunction) => false,
            \is_string($testFunction) && \function_exists($testFunction) => true,
            default => throw new InvalidArgument('Invalid test function provided.'),
        };
        $isFunction or \method_exists($testFunction[0], $testFunction[1]) or throw new InvalidArgument(
            'Invalid test method provided.',
        );
        return $isFunction;
    }
}
