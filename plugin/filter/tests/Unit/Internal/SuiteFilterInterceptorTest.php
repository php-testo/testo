<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Internal;

use Internal\Path;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Filter\Internal\SuiteFilterInterceptor;
use Testo\Test;

#[Test]
#[Covers(SuiteFilterInterceptor::class)]
final class SuiteFilterInterceptorTest
{
    private const ROOT = __DIR__ . '/../Fixture/Suite';

    public function passesEverySuiteWithoutFilters(): void
    {
        $config = self::config(['alpha' => self::ROOT . '/Alpha', 'beta' => self::ROOT . '/Beta']);

        $suites = self::locate(new Filter(), $config);

        Assert::same($suites, $config->suites);
    }

    public function dropsSuitesNotSelectedByName(): void
    {
        $config = self::config(['alpha' => self::ROOT . '/Alpha', 'beta' => self::ROOT . '/Beta']);

        $suites = self::locate(new Filter(suites: ['beta']), $config);

        Assert::same(self::names($suites), ['beta']);
    }

    /**
     * A filtered path inside an include root replaces that root, so the scan starts right there.
     */
    public function narrowsIncludeRootToFilteredPathInsideIt(): void
    {
        $alpha = self::ROOT . '/Alpha';

        $suites = self::locate(new Filter(paths: [$alpha]), self::config(['all' => self::ROOT]));

        Assert::same(self::names($suites), ['all']);
        Assert::same(self::includes($suites[0]), [self::absolute($alpha)]);
    }

    /**
     * An include root inside a filtered path is already narrow enough and stays as is.
     */
    public function keepsIncludeRootInsideFilteredPath(): void
    {
        $alpha = self::ROOT . '/Alpha';

        $suites = self::locate(new Filter(paths: [self::ROOT]), self::config(['alpha' => $alpha]));

        Assert::same(self::includes($suites[0]), [self::absolute($alpha)]);
    }

    public function dropsSuiteWhoseRootsTouchNoFilteredPath(): void
    {
        $config = self::config(['alpha' => self::ROOT . '/Alpha', 'beta' => self::ROOT . '/Beta']);

        $suites = self::locate(new Filter(paths: [self::ROOT . '/Beta']), $config);

        Assert::same(self::names($suites), ['beta']);
    }

    /**
     * @return list<SuiteConfig>
     */
    private static function locate(Filter $filter, ApplicationConfig $config): array
    {
        return (new SuiteFilterInterceptor($filter))->locateTestSuites(
            $config,
            static fn(ApplicationConfig $c): array => $c->suites,
        );
    }

    /**
     * @param non-empty-array<non-empty-string, string> $suites Suite name to its include root.
     */
    private static function config(array $suites): ApplicationConfig
    {
        $configs = [];
        foreach ($suites as $name => $include) {
            $configs[] = new SuiteConfig($name, [$include]);
        }

        return new ApplicationConfig(suites: $configs);
    }

    /**
     * @param list<SuiteConfig> $suites
     * @return list<string>
     */
    private static function names(array $suites): array
    {
        return \array_map(static fn(SuiteConfig $s): string => $s->name, $suites);
    }

    /**
     * @return list<string>
     */
    private static function includes(SuiteConfig $suite): array
    {
        return \array_map(strval(...), $suite->location->includes);
    }

    private static function absolute(string $path): string
    {
        return (string) Path::create($path)->absolute();
    }
}
