<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Unit;

use Internal\Container\Container;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Internal\Mock\MockContainer;
use Tests\Core\Testing\Stub\Plugin\DestroyableStubService;
use Tests\Core\Testing\Stub\Plugin\StubService;

#[Test]
#[Covers(MockContainer::class)]
final class MockContainerTest
{
    public function recordsRegistrationsAndStillResolvesThem(): void
    {
        $container = new MockContainer();
        $service = new StubService();

        $container->set($service);

        Assert::same($container->registrations[0]['id'], StubService::class);
        Assert::same($container->get(StubService::class), $service);
    }

    public function recordsBindingsAndReportsThemAsAvailable(): void
    {
        $container = new MockContainer();

        $container->bind(StubService::class, StubService::class);

        Assert::same($container->bindings[0]['id'], StubService::class);
        Assert::true($container->has(StubService::class));
    }

    public function presetSeedsAServiceWithoutRecordingIt(): void
    {
        $container = new MockContainer();
        $service = new StubService();

        $container->preset($service);

        Assert::same($container->get(StubService::class), $service);
        Assert::count($container->registrations, 0);
    }

    public function makeBuildsAFreshInstanceWithoutStoringIt(): void
    {
        $container = new MockContainer();

        $made = $container->make(StubService::class);

        Assert::instanceOf($made, StubService::class);
        Assert::false($container->has(StubService::class));
    }

    public function scopeRunsTheClosureAndReturnsItsValue(): void
    {
        $container = new MockContainer();

        $result = $container->scope(static fn(Container $c): int => $c->has(StubService::class) ? 1 : 42);

        Assert::same($result, 42);
    }

    public function destroyReachesManagedServices(): void
    {
        $container = new MockContainer();
        $service = new DestroyableStubService();
        $container->set($service, destroy: true);

        $container->destroy();

        Assert::true($service->destroyed);
    }
}
