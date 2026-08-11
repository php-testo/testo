<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Internal;

use Internal\Container\ObjectContainer;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Application\Internal\MessengerHub;
use Testo\Application\Internal\SuiteFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\ErrorReporter;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Test;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Tests\Common\Stub\SpyDispatcher;

/**
 * {@see \Tests\Application\Feature\Runner\EmptyRunTest} already covers the end-to-end "no tests found"
 * path through {@see \Testo\Application\Application}. This constructs {@see SuiteFactory} directly and
 * points it at a real, discoverable fixture case, so the actual file/case discovery machinery in
 * {@see SuiteFactory::create()} — not just the degenerate empty path — runs under a direct call.
 */
#[Test]
#[Covers(SuiteFactory::class)]
final class SuiteFactoryTest
{
    public function createReturnsSuiteInfoWithDiscoveredCase(): void
    {
        $factory = self::makeFactory();
        $config = new SuiteConfig(
            name: 'SuiteFactoryFixture',
            location: new FinderConfig(include: [self::fixtureDir()]),
        );

        $info = $factory->create($config, new Filter());

        Assert::same($info->name, 'SuiteFactoryFixture');
        $cases = $info->testCases->getCases();
        Assert::same(\count($cases), 1);
        Assert::same($cases[0]->reflection?->getName(), 'Tests\Application\Stub\SuiteFactoryFixture\FixtureCase');
    }

    public function createReturnsEmptySuiteInfoWhenNoFilesMatch(): void
    {
        $factory = self::makeFactory();
        $config = new SuiteConfig(
            name: 'EmptyFixture',
            location: new FinderConfig(include: [__DIR__ . '/../../../Application/Stub/EmptyRun']),
        );

        $info = $factory->create($config, new Filter());

        Assert::same($info->testCases->getCases(), []);
    }

    private static function makeFactory(): SuiteFactory
    {
        $container = new ObjectContainer();
        $provider = new InterceptorProvider($container);
        $provider->addInterceptor(new TestoAttributesLocatorInterceptor());

        $reporter = new ErrorReporter(new MessengerHub(new SpyDispatcher()));

        return new SuiteFactory($provider, $reporter);
    }

    private static function fixtureDir(): string
    {
        return __DIR__ . '/../../Stub/SuiteFactoryFixture';
    }
}
