<?php

declare(strict_types=1);

namespace Testo\Testing\Helper;

use Internal\Container\Container;
use Testo\Assert;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\Interceptor;
use Testo\Pipeline\InterceptorCollector;
use Testo\Testing\Internal\Collector\RecordingEventListenerCollector;
use Testo\Testing\Internal\Collector\RecordingInterceptorCollector;
use Testo\Testing\Internal\Mock\MockContainer;

/**
 * Drives a {@see PluginConfigurator} through its {@see PluginConfigurator::configure()} against recording
 * collectors and a {@see MockContainer}, then exposes fluent assertions over what it registered.
 *
 * ```php
 * PluginTester::for(new MyPlugin())
 *     ->addsInterceptor(MyInterceptor::class)
 *     ->addsListener(SessionFinished::class)
 *     ->binds(SomeService::class);
 * ```
 *
 * Every assertion returns `$this` and reports through the {@see Assert} facade, so a plugin test needs no
 * assertions of its own. For checks the fluent methods don't cover, read the raw records via
 * {@see self::interceptors()}, {@see self::listeners()}, {@see self::bindings()} and {@see self::container()}.
 *
 * `configure()` never runs the interceptors or listeners it captures, so coverage is not attributed on its
 * own. Declare `#[Covers]` on the test for the plugin and the code it wires (its interceptors, listeners,
 * bound services) so those classes are credited.
 *
 * @internal
 * @psalm-internal Testo
 */
final readonly class PluginTester
{
    private function __construct(
        private RecordingInterceptorCollector $interceptors,
        private RecordingEventListenerCollector $listeners,
        private MockContainer $container,
    ) {}

    /**
     * Configure the plugin and capture what it registers.
     *
     * @param Container|null $inner Backing container for services the plugin resolves beyond the collectors
     *        (e.g. a CLI input, an event dispatcher). Defaults to a fresh autowiring container.
     */
    public static function for(PluginConfigurator $plugin, ?Container $inner = null): self
    {
        $interceptors = new RecordingInterceptorCollector();
        $listeners = new RecordingEventListenerCollector();
        $container = new MockContainer($inner);
        $container->preset($interceptors, InterceptorCollector::class);
        $container->preset($listeners, EventListenerCollector::class);

        $plugin->configure($container);

        return new self($interceptors, $listeners, $container);
    }

    /**
     * Assert the plugin added an interceptor of the given class, whether it passed an instance or a
     * class-string.
     *
     * @param class-string<Interceptor> $interceptor
     */
    public function addsInterceptor(string $interceptor): self
    {
        $found = false;
        foreach ($this->interceptorClasses() as $class) {
            if ($class === $interceptor || \is_a($class, $interceptor, true)) {
                $found = true;
                break;
            }
        }

        Assert::true($found, \sprintf(
            'Plugin did not add interceptor %s. Added: %s',
            $interceptor,
            $this->describe($this->interceptorClasses()),
        ));

        return $this;
    }

    /**
     * Assert the plugin added exactly this many interceptors.
     */
    public function addsInterceptors(int $count): self
    {
        Assert::count($this->interceptors->interceptors, $count);

        return $this;
    }

    /**
     * Assert the plugin registered a listener for the given event class.
     *
     * @param class-string $eventName
     */
    public function addsListener(string $eventName): self
    {
        $found = false;
        foreach ($this->listeners->listeners as $listener) {
            if ($listener['event'] === $eventName) {
                $found = true;
                break;
            }
        }

        Assert::true($found, \sprintf(
            'Plugin did not add a listener for %s. Listened: %s',
            $eventName,
            $this->describe(\array_column($this->listeners->listeners, 'event')),
        ));

        return $this;
    }

    /**
     * Assert the plugin added exactly this many listeners.
     */
    public function addsListeners(int $count): self
    {
        Assert::count($this->listeners->listeners, $count);

        return $this;
    }

    /**
     * Assert the plugin declared a binding for the given service id via {@see Container::bind()}.
     *
     * @param class-string $id
     */
    public function binds(string $id): self
    {
        Assert::contains(\array_column($this->container->bindings, 'id'), $id, \sprintf(
            'Plugin did not bind %s.',
            $id,
        ));

        return $this;
    }

    /**
     * Assert the plugin registered a service instance for the given id via {@see Container::set()}.
     *
     * @param class-string $id
     */
    public function registers(string $id): self
    {
        Assert::contains(\array_column($this->container->registrations, 'id'), $id, \sprintf(
            'Plugin did not register a service for %s.',
            $id,
        ));

        return $this;
    }

    /**
     * The interceptors the plugin added, each as the instance or class-string it passed.
     *
     * @return list<Interceptor|class-string<Interceptor>>
     */
    public function interceptors(): array
    {
        return $this->interceptors->interceptors;
    }

    /**
     * The listeners the plugin registered.
     *
     * @return list<array{event: class-string, callback: callable, priority: int}>
     */
    public function listeners(): array
    {
        return $this->listeners->listeners;
    }

    /**
     * The bindings the plugin declared via {@see Container::bind()}.
     *
     * @return list<array{id: class-string, binding: \Closure|class-string|array<string, mixed>|null}>
     */
    public function bindings(): array
    {
        return $this->container->bindings;
    }

    public function container(): MockContainer
    {
        return $this->container;
    }

    /**
     * @return list<class-string>
     */
    private function interceptorClasses(): array
    {
        return \array_map(
            static fn(Interceptor|string $i): string => \is_object($i) ? $i::class : $i,
            $this->interceptors->interceptors,
        );
    }

    /**
     * @param list<string> $items
     */
    private function describe(array $items): string
    {
        return $items === [] ? '(none)' : \implode(', ', $items);
    }
}
