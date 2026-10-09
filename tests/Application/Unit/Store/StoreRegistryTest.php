<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Store;

use Internal\Container\ObjectContainer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\StoreConfig;
use Testo\Application\Internal\MessengerHub;
use Testo\Application\Internal\Store\NullStore;
use Testo\Application\Internal\StoreRegistry;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Common\Store\StoreDefinition;
use Testo\Common\Store\StoreScope;
use Testo\Core\Context\SuiteContext;
use Testo\Expect;
use Testo\Test;

#[Covers(StoreRegistry::class)]
final class StoreRegistryTest
{
    #[Test]
    public function suiteScopedStoreRoundTripsWithinASuite(): void
    {
        $this->inTempDir(function (string $dir): void {
            $registry = $this->registry($dir, suite: 'Core/Inline');
            $store = $registry->open(new StoreDefinition('impact.index', 1, StoreScope::Suite));

            $store->save(['selected' => ['a', 'b']]);

            Assert::same($store->load(), ['selected' => ['a', 'b']]);
        });
    }

    #[Test]
    public function applicationScopedStoreRoundTripsWithoutASuite(): void
    {
        $this->inTempDir(function (string $dir): void {
            $registry = $this->registry($dir, suite: null);
            $store = $registry->open(new StoreDefinition('timing.durations', 1, StoreScope::Application));

            $store->save(['x' => 1]);

            Assert::same($store->load(), ['x' => 1]);
        });
    }

    #[Test]
    public function oneSuiteCannotSeeAnotherSuitesData(): void
    {
        $this->inTempDir(function (string $dir): void {
            $container = $this->container('Suite A');
            $registry = new StoreRegistry($container, new StoreConfig(directory: $dir), $this->messenger());
            $definition = new StoreDefinition('impact.index', 1, StoreScope::Suite);

            $registry->open($definition)->save(['owner' => 'A']);
            $container->set(new SuiteContext('Suite B'));

            Assert::null($registry->open($definition)->load());
        });
    }

    #[Test]
    public function openingASuiteStoreOutsideASuiteScopeThrows(): never
    {
        $this->inTempDir(function (string $dir): void {
            $registry = $this->registry($dir, suite: null);

            Expect::exception(\LogicException::class);

            $registry->open(new StoreDefinition('impact.index', 1, StoreScope::Suite));
        });
    }

    #[Test]
    public function theScopeCheckFiresEvenWhenStoresAreDisabled(): never
    {
        $this->inTempDir(function (string $dir): void {
            # A misplaced open() is a plugin bug; it must not stay hidden on configurations
            # that happen to have stores turned off.
            $registry = $this->registry($dir, suite: null, enabled: false);

            Expect::exception(\LogicException::class);

            $registry->open(new StoreDefinition('impact.index', 1, StoreScope::Suite));
        });
    }

    #[Test]
    public function disabledSubsystemHandsOutANoDataStore(): void
    {
        $this->inTempDir(function (string $dir): void {
            $registry = $this->registry($dir, suite: 'Core/Inline', enabled: false);
            $store = $registry->open(new StoreDefinition('impact.index', 1, StoreScope::Suite));

            $store->save(['v' => 1]);

            Assert::instanceOf($store, NullStore::class);
            Assert::null($store->load());
            Assert::false(\is_dir($dir . '/suite'));
        });
    }

    private function registry(string $dir, ?string $suite, bool $enabled = true): StoreRegistry
    {
        return new StoreRegistry(
            $this->container($suite),
            new StoreConfig(directory: $dir, enabled: $enabled),
            $this->messenger(),
        );
    }

    private function container(?string $suite): ObjectContainer
    {
        $container = new ObjectContainer();
        $suite === null or $container->set(new SuiteContext($suite));

        return $container;
    }

    private function messenger(): Messenger
    {
        return new MessengerHub(new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        });
    }

    /**
     * @param \Closure(string): void $test
     */
    private function inTempDir(\Closure $test): void
    {
        $dir = \sys_get_temp_dir() . '/testo-registry-' . \bin2hex(\random_bytes(6));
        \mkdir($dir, 0777, true);

        try {
            $test($dir);
        } finally {
            $this->removeDir($dir);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @\rmdir($item->getPathname()) : @\unlink($item->getPathname());
        }
        @\rmdir($dir);
    }
}
