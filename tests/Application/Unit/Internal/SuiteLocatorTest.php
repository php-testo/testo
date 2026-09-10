<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Internal;

use Internal\Container\ObjectContainer;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Application\Internal\SuiteLocator;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\SuiteLocatorInterceptor;
use Testo\Test;

#[Test]
#[Covers(SuiteLocator::class)]
final class SuiteLocatorTest
{
    public function yieldsConfiguredSuitesWithoutInterceptors(): void
    {
        $config = self::config('first', 'second');

        $suites = self::locator(new ObjectContainer())->locate($config);

        Assert::same($suites, $config->suites);
    }

    public function runsRegisteredInterceptorsOverTheList(): void
    {
        $container = new ObjectContainer();
        $container->get(InterceptorProvider::class)->addInterceptor(new class implements SuiteLocatorInterceptor {
            public function locateTestSuites(ApplicationConfig $config, callable $next): array
            {
                return \array_reverse($next($config));
            }
        });
        $config = self::config('first', 'second');

        $suites = self::locator($container)->locate($config);

        Assert::same(
            \array_map(static fn(SuiteConfig $s): string => $s->name, $suites),
            ['second', 'first'],
        );
    }

    public function interceptorMayAddSuitesAbsentFromConfig(): void
    {
        $container = new ObjectContainer();
        $container->get(InterceptorProvider::class)->addInterceptor(new class implements SuiteLocatorInterceptor {
            public function locateTestSuites(ApplicationConfig $config, callable $next): array
            {
                return [...$next($config), new SuiteConfig('synthetic', [__DIR__])];
            }
        });

        $suites = self::locator($container)->locate(self::config('first'));

        Assert::array($suites)->hasCount(2);
        Assert::same($suites[1]->name, 'synthetic');
    }

    private static function locator(ObjectContainer $container): SuiteLocator
    {
        return new SuiteLocator($container->get(InterceptorProvider::class), new Filter());
    }

    /**
     * @param non-empty-string ...$names
     */
    private static function config(string ...$names): ApplicationConfig
    {
        return new ApplicationConfig(suites: \array_map(
            static fn(string $name): SuiteConfig => new SuiteConfig($name, [__DIR__]),
            $names,
        ));
    }
}
