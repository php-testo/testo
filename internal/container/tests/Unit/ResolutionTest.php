<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit;

use Internal\Container\Container;
use Internal\Container\ObjectContainer;
use Internal\Container\Tests\Unit\Stub\ConfigFactoriable;
use Internal\Container\Tests\Unit\Stub\ContainerScopeService;
use Internal\Container\Tests\Unit\Stub\DependentService;
use Internal\Container\Tests\Unit\Stub\DestroyableService;
use Internal\Container\Tests\Unit\Stub\Greeter;
use Internal\Container\Tests\Unit\Stub\GreeterInterface;
use Internal\Container\Tests\Unit\Stub\TagInflector;
use Internal\Container\Tests\Unit\Stub\UnresolvableService;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * Core {@see ObjectContainer} behaviour: resolution, caching, registration, bindings, inflectors and
 * teardown — the synchronous surface every scope builds on.
 */
#[Test]
#[Covers(ObjectContainer::class)]
final class ResolutionTest
{
    public function getCachesTheSameInstance(): void
    {
        $container = new ObjectContainer();

        Assert::same(
            $container->get(ContainerScopeService::class),
            $container->get(ContainerScopeService::class),
        );
    }

    public function makeCreatesAFreshInstanceEachTime(): void
    {
        $container = new ObjectContainer();

        $first = $container->make(ContainerScopeService::class);
        $second = $container->make(ContainerScopeService::class);

        Assert::notSame($first, $second);
        Assert::notSame($container->get(ContainerScopeService::class), $first);
    }

    public function hasIsFalseUntilResolvedOrBound(): void
    {
        $container = new ObjectContainer();

        Assert::false($container->has(GreeterInterface::class));

        $container->bind(GreeterInterface::class, Greeter::class);
        Assert::true($container->has(GreeterInterface::class));
    }

    public function hasIsTrueAfterResolution(): void
    {
        $container = new ObjectContainer();

        Assert::false($container->has(ContainerScopeService::class));
        $container->get(ContainerScopeService::class);
        Assert::true($container->has(ContainerScopeService::class));
    }

    public function setStoresAServiceUnderItsClass(): void
    {
        $container = new ObjectContainer();
        $service = new ContainerScopeService();
        $service->tag = 5;

        $container->set($service);

        Assert::same($container->get(ContainerScopeService::class), $service);
    }

    public function setStoresAServiceUnderAnExplicitId(): void
    {
        $container = new ObjectContainer();
        $greeter = new Greeter();

        $container->set($greeter, GreeterInterface::class);

        Assert::same($container->get(GreeterInterface::class), $greeter);
    }

    public function autowiresConstructorDependencies(): void
    {
        $container = new ObjectContainer();

        $dependent = $container->get(DependentService::class);

        Assert::instanceOf($dependent->dependency, ContainerScopeService::class);
        Assert::same($dependent->dependency, $container->get(ContainerScopeService::class));
    }

    public function bindUsesAClosureAsFactory(): void
    {
        $container = new ObjectContainer();
        $container->bind(ContainerScopeService::class, static function (): ContainerScopeService {
            $service = new ContainerScopeService();
            $service->tag = 55;
            return $service;
        });

        Assert::same($container->get(ContainerScopeService::class)->tag, 55);
    }

    public function bindResolvesAClassAliasToItsImplementation(): void
    {
        $container = new ObjectContainer();
        $container->bind(GreeterInterface::class, Greeter::class);

        Assert::instanceOf($container->get(GreeterInterface::class), Greeter::class);
    }

    public function bindSuppliesConstructorArgumentsFromAnArray(): void
    {
        $container = new ObjectContainer();
        $container->bind(UnresolvableService::class, ['count' => 5]);

        Assert::same($container->get(UnresolvableService::class)->count, 5);
    }

    public function bindWithNullSelfBinds(): void
    {
        $container = new ObjectContainer();
        $container->bind(ContainerScopeService::class);

        Assert::true($container->has(ContainerScopeService::class));
        Assert::instanceOf($container->get(ContainerScopeService::class), ContainerScopeService::class);
    }

    public function getPassesFirstTimeArgumentsToTheConstructor(): void
    {
        $container = new ObjectContainer();

        Assert::same($container->get(UnresolvableService::class, ['count' => 9])->count, 9);
    }

    public function buildsFactoriableServicesThroughTheirCreateMethod(): void
    {
        $container = new ObjectContainer();
        $container->bind(ConfigFactoriable::class);

        $config = $container->get(ConfigFactoriable::class);

        Assert::instanceOf($config, ConfigFactoriable::class);
        Assert::instanceOf($config->service, ContainerScopeService::class);
    }

    public function runsInflectorsOverResolvedServices(): void
    {
        $container = new ObjectContainer();
        $container->addInflector(new TagInflector());

        Assert::same($container->make(ContainerScopeService::class)->tag, 100);
    }

    public function resolvesTheContainerInterfaceToItself(): void
    {
        $container = new ObjectContainer();

        Assert::same($container->get(ContainerInterface::class), $container);
        Assert::same($container->get(Container::class), $container);
        Assert::same($container->get(ObjectContainer::class), $container);
    }

    public function destroyTearsDownManagedDestroyableServices(): void
    {
        $container = new ObjectContainer();
        $service = $container->get(DestroyableService::class);

        $container->destroy();

        Assert::true($service->destroyed);
    }

    public function makeThrowsNotFoundForAnUnresolvableService(): never
    {
        $container = new ObjectContainer();

        Expect::exception(NotFoundExceptionInterface::class);

        $container->make(UnresolvableService::class);
    }

    public function bindRejectsANonExistentAliasClass(): never
    {
        $container = new ObjectContainer();

        Expect::exception(\InvalidArgumentException::class);

        $container->bind(GreeterInterface::class, 'No\\Such\\Class');
    }

    public function bindRejectsAnIncompatibleAlias(): never
    {
        $container = new ObjectContainer();

        Expect::exception(\InvalidArgumentException::class);

        $container->bind(GreeterInterface::class, ContainerScopeService::class);
    }
}
