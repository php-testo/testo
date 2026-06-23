<?php

declare(strict_types=1);

namespace Tests\Spec\Unit;

use Internal\Container\Container;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Pipeline\Interceptor;
use Testo\Pipeline\InterceptorCollector;
use Testo\Spec\Internal\SpecCaseOrderInterceptor;
use Testo\Spec\Internal\SpecCollector;
use Testo\Spec\Internal\SpecInput;
use Testo\Spec\Internal\SpecSuiteOrderInterceptor;
use Testo\Spec\SpecPlugin;
use Testo\Test;

#[Test]
#[Covers(SpecPlugin::class)]
final class SpecPluginTest
{
    public function reorderingIsRegisteredByDefault(): void
    {
        $container = self::container(new SpecInput());

        (new SpecPlugin())->configure($container);

        Assert::same($container->interceptors, [SpecSuiteOrderInterceptor::class, SpecCaseOrderInterceptor::class]);
    }

    public function reorderingCanBeDisabled(): void
    {
        $container = self::container(new SpecInput());

        (new SpecPlugin(reorder: false))->configure($container);

        Assert::same($container->interceptors, []);
    }

    public function generationStaysOffByDefault(): void
    {
        $container = self::container(new SpecInput());

        (new SpecPlugin())->configure($container);

        Assert::false($container->has(SpecCollector::class));
        Assert::same($container->listenedEvents, []);
    }

    public function generationActivatesWithTheCollectFlag(): void
    {
        $container = self::container(new SpecInput());

        (new SpecPlugin(collect: true))->configure($container);

        Assert::true($container->has(SpecCollector::class));
        Assert::same($container->listenedEvents, [TestSuiteFinished::class]);
    }

    public function generationActivatesFromTheCliFlag(): void
    {
        $input = new SpecInput();
        $input->spec = true;
        $container = self::container($input);

        (new SpecPlugin())->configure($container);

        Assert::true($container->has(SpecCollector::class));
    }

    public function cliDirectoryOverridesConfiguredDirectory(): void
    {
        $input = new SpecInput();
        $input->dir = 'cli/dir';
        $container = self::container($input);

        (new SpecPlugin(outputDir: 'config/dir', collect: true))->configure($container);

        $collector = $container->services[SpecCollector::class];
        $dir = (new \ReflectionProperty(SpecCollector::class, 'outputDir'))->getValue($collector);
        Assert::same($dir, 'cli/dir');
    }

    public function generationDoesNotRegisterTwice(): void
    {
        $input = new SpecInput();
        $input->spec = true;
        $container = self::container($input);
        $container->services[SpecCollector::class] = new SpecCollector('existing');

        (new SpecPlugin(reorder: false))->configure($container);

        Assert::same($container->listenedEvents, []);
    }

    private static function container(SpecInput $input): Container
    {
        return new class($input) implements Container {
            /** @var array<class-string, object> */
            public array $services = [];

            /** @var list<class-string> */
            public array $listenedEvents = [];

            /** @var list<class-string> */
            public array $interceptors = [];

            public function __construct(private readonly SpecInput $input)
            {
                $owner = $this;
                $this->services[EventListenerCollector::class] = new class($owner) implements EventListenerCollector {
                    public function __construct(private readonly object $owner) {}

                    #[\Override]
                    public function addListener(string $eventName, callable $callback, int $priority = 0): void
                    {
                        $this->owner->listenedEvents[] = $eventName;
                    }
                };
                $this->services[InterceptorCollector::class] = new class($owner) implements InterceptorCollector {
                    public function __construct(private readonly object $owner) {}

                    #[\Override]
                    public function addInterceptor(Interceptor|string $interceptor): void
                    {
                        $this->owner->interceptors[] = \is_string($interceptor) ? $interceptor : $interceptor::class;
                    }
                };
            }

            #[\Override]
            public function get(string $id, array $arguments = []): object
            {
                return $id === SpecInput::class ? $this->input : $this->services[$id];
            }

            #[\Override]
            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }

            #[\Override]
            public function set(object $service, ?string $id = null, bool $destroy = false): void
            {
                $this->services[$id ?? $service::class] = $service;
            }

            #[\Override]
            public function make(string $class, array $arguments = []): object
            {
                return $this->get($class);
            }

            #[\Override]
            public function bind(string $id, \Closure|string|array|null $binding = null): void {}

            #[\Override]
            public function scope(\Closure $scope): mixed
            {
                return $scope($this);
            }

            #[\Override]
            public function destroy(): void {}
        };
    }
}
