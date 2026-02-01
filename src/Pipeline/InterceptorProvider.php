<?php

declare(strict_types=1);

namespace Testo\Pipeline;

use Testo\Application\Middleware\AttributesInterceptor;
use Testo\Application\Middleware\FilterInterceptor;
use Testo\Application\Middleware\Locator\FilePostfixTestLocatorInterceptor;
use Testo\Application\Middleware\Locator\TestoAttributesLocatorInterceptor;
use Testo\Assert\Interceptor\AssertCollectorInterceptor;
use Testo\Assert\Interceptor\ExpectationsInterceptor;
use Testo\Common\Container;
use Testo\Common\Reflection;
use Testo\Inline\Middleware\TestInlineFinder;
use Testo\Lifecycle\Interceptor\LifecycleInterceptor;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Internal\InterceptorMarker;
use Yiisoft\Injector\Injector;

final class InterceptorProvider
{
    /**
     * Map of interceptable attributes to their interceptors.
     * @var array<class-string<Interceptable>, null|class-string<InterceptorMarker>>
     */
    private array $map = [];

    private readonly Injector $injector;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->injector = $this->container->get(Injector::class)->withCacheReflections(true);
    }

    public static function createDefault(Container $container): self
    {
        $self = new self($container);
        $self->map = [];
        return $self;
    }

    /**
     * Get interceptors for the given configuration filtered by the given class.
     *
     * @template-covariant T of InterceptorMarker
     *
     * @param class-string<T> $class The target interceptor class.
     *
     * @return InterceptorMarker Interceptor instances of the given class.
     */
    public function fromConfig(string $class): array
    {
        return $this->fromClasses($class, ...[
            FilterInterceptor::class,
            TestInlineFinder::class,
            new FilePostfixTestLocatorInterceptor(),
            new TestoAttributesLocatorInterceptor(),
            new AssertCollectorInterceptor(),
            AttributesInterceptor::class,
            new ExpectationsInterceptor(),
            LifecycleInterceptor::class,
        ]);
    }

    /**
     * Get interceptors for
     *
     * @template-covariant T of InterceptorMarker
     *
     * @param class-string<T> $class The target interceptor class.
     * @param class-string<InterceptorMarker>|InterceptorMarker ...$interceptors Interceptor classes or instances
     *        to filter by the given class.
     *
     * @return InterceptorMarker Interceptor instances of the given class.
     */
    public function fromClasses(string $class, string|InterceptorMarker ...$interceptors): array
    {
        $result = [];
        foreach ($interceptors as $interceptor) {
            if (\is_string($interceptor)) {
                if (\class_exists($interceptor) && !\is_a($interceptor, $class, true)) {
                    continue;
                }

                $interceptor = $this->container->get($interceptor);
            }

            $interceptor instanceof $class and $result[] = $interceptor;
        }
        return $result;
    }

    /**
     * Get interceptors for the given attributes set filtered by the given class.
     *
     * @template-covariant T of InterceptorMarker
     *
     * @param class-string<T> $class The target interceptor class.
     * @param Interceptable ...$attributes Attributes to get interceptors for.
     *
     * @return InterceptorMarker Interceptors for the given attributes.
     */
    public function fromAttributes(string $class, Interceptable ...$attributes): array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            # Get alias interceptor
            $iClass = $this->resolveAlias($attribute::class) ?? throw new \RuntimeException(
                \sprintf('No interceptor found for attribute %s.', $attribute::class),
            );

            \is_a($iClass, $class, true) and $result[] = $this->createInstance($iClass, [$attribute]);
        }

        return $result;
    }

    /**
     * Creates an instance of the given class with the given arguments.
     *
     * @template T of InterceptorMarker
     *
     * @param class-string<T> $class The class to create.
     * @param array $arguments The arguments to pass to the constructor.
     *
     * @return InterceptorMarker The created instance.
     */
    private function createInstance(string $class, array $arguments = []): InterceptorMarker
    {
        return $this->injector->make($class, $arguments);
    }

    /**
     * Resolve alias interceptor for the given attribute class.
     *
     * @param class-string<Interceptable> $class The attribute class.
     * @return class-string<InterceptorMarker>|null The interceptor class or null if not found.
     */
    private function resolveAlias(string $class): ?string
    {
        $c = $class;
        do {
            if (\array_key_exists($c, $this->map)) {
                return $this->map[$c];
            }

            $c = \get_parent_class($c);
        } while ($c);

        /**
         * Resolve fallback handler from the {@see FallbackInterceptor} attribute
         * @var list<\ReflectionAttribute<FallbackInterceptor>> $attrs
         */
        $attrs = Reflection::fetchClassAttributes($class, attributeClass: FallbackInterceptor::class);

        return $this->map[$class] ??= $attrs === [] ? null : $attrs[0]->newInstance()->class;
    }
}
