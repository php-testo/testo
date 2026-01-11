<?php

declare(strict_types=1);

namespace Tests\Testo\Factory;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Expect;
use Testo\Test\Factory\ContainerFactory;
use Tests\Fixture\Factory\Dependency;
use Tests\Fixture\Factory\SimpleTestCase;
use Tests\Fixture\Factory\TestCaseWithDependencies;

final class ContainerFactoryTest
{
    #[Test(description: 'Creates instance using PSR-11 container.')]
    public function itCreatesInstanceUsingContainer(): void
    {
        $instance = new SimpleTestCase();
        $container = new class($instance) implements ContainerInterface {
            public function __construct(private readonly object $instance) {}

            public function get(string $id): object
            {
                if ($id === SimpleTestCase::class) {
                    return $this->instance;
                }
                throw new class extends \Exception implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return $id === SimpleTestCase::class;
            }
        };

        $factory = new ContainerFactory($container);
        $reflection = new \ReflectionClass(SimpleTestCase::class);
        $result = $factory->create($reflection);

        Assert::same($instance, $result);
    }

    #[Test(description: 'Creates instance with dependencies resolved from container.')]
    public function itResolvesDependenciesFromContainer(): void
    {
        $dependency = new Dependency();
        $testCase = new TestCaseWithDependencies($dependency);

        $container = new class($dependency, $testCase) implements ContainerInterface {
            public function __construct(
                private readonly Dependency $dependency,
                private readonly TestCaseWithDependencies $testCase,
            ) {}

            public function get(string $id): object
            {
                return match ($id) {
                    Dependency::class => $this->dependency,
                    TestCaseWithDependencies::class => $this->testCase,
                    default => throw new class extends \Exception implements NotFoundExceptionInterface {},
                };
            }

            public function has(string $id): bool
            {
                return in_array($id, [Dependency::class, TestCaseWithDependencies::class], true);
            }
        };

        $factory = new ContainerFactory($container);
        $reflection = new \ReflectionClass(TestCaseWithDependencies::class);
        $result = $factory->create($reflection);

        Assert::same($testCase, $result);
        Assert::same($dependency, $result->dependency);
    }

    #[Test(description: 'Throws not found exception when class not in container.')]
    public function itThrowsNotFoundExceptionWhenClassNotInContainer(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                throw new class extends \Exception implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $factory = new ContainerFactory($container);
        $reflection = new \ReflectionClass(SimpleTestCase::class);

        Expect::exception(NotFoundExceptionInterface::class);
        $factory->create($reflection);
    }

    #[Test(description: 'Throws container exception when container encounters error.')]
    public function itThrowsContainerExceptionWhenContainerEncountersError(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): object
            {
                throw new class extends \Exception implements \Psr\Container\ContainerExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $factory = new ContainerFactory($container);
        $reflection = new \ReflectionClass(SimpleTestCase::class);

        Expect::exception(\Psr\Container\ContainerExceptionInterface::class);
        $factory->create($reflection);
    }

    #[Test(description: 'Ignores constructor arguments as container resolves dependencies.')]
    public function itIgnoresConstructorArguments(): void
    {
        $instance = new SimpleTestCase();
        $container = new class($instance) implements ContainerInterface {
            public function __construct(private readonly object $instance) {}

            public function get(string $id): object
            {
                return $this->instance;
            }

            public function has(string $id): bool
            {
                return true;
            }
        };

        $factory = new ContainerFactory($container);
        $reflection = new \ReflectionClass(SimpleTestCase::class);
        $result = $factory->create($reflection, ['ignored', 'args']);

        Assert::same($instance, $result);
    }
}
