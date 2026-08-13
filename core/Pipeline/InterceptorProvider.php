<?php

declare(strict_types=1);

namespace Testo\Pipeline;

use Internal\Container\Container;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Internal\AttributesInterceptor;
use Testo\Pipeline\Internal\Cache;
use Yiisoft\Injector\Injector;

/**
 * @api
 */
final class InterceptorProvider implements InterceptorCollector
{
    private array $interceptors = [
        AttributesInterceptor::class, // todo: make it optional and move to a separate plugin?
    ];
    private readonly Injector $injector;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->injector = $this->container->get(Injector::class)->withCacheReflections(true);
    }

    #[\Override]
    public function addInterceptor(Interceptor|string $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }

    /**
     * Get interceptors for the given configuration filtered by the given class.
     *
     * @template T of Interceptor
     *
     * @param class-string<T> $class The target interceptor class.
     *
     * @return list<T> Interceptor instances of the given class.
     */
    public function fromConfig(string $class): array
    {
        return $this->fromClasses($class, ...$this->interceptors);
    }

    /**
     * Get interceptors for
     *
     * @template T of Interceptor
     *
     * @param class-string<T> $class The target interceptor class.
     * @param class-string<Interceptor>|Interceptor ...$interceptors Interceptor classes or instances
     *        to filter by the given class.
     *
     * @return list<T> Interceptor instances of the given class.
     */
    public function fromClasses(string $class, string|Interceptor ...$interceptors): array
    {
        $result = [];
        foreach ($interceptors as $interceptor) {
            if (\is_string($interceptor)) {
                if (!\class_exists($interceptor) || !\is_a($interceptor, $class, true)) {
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
     * @template T of Interceptor
     *
     * @param class-string<T> $class The target interceptor class.
     * @param Interceptable ...$attributes Attributes to get interceptors for.
     *
     * @return list<T> Interceptors for the given attributes.
     */
    public function fromAttributes(string $class, Interceptable ...$attributes): array
    {
        $result = [];

        foreach ($attributes as $attribute) {
            # Get alias interceptors
            $iClasses = Cache::resolveAliases($attribute::class);
            $iClasses === [] and throw new \RuntimeException(
                \sprintf('No interceptor found for attribute %s.', $attribute::class),
            );

            foreach ($iClasses as $iClass) {
                \is_a($iClass, $class, true) and $result[] = $this->createInstance($iClass, [$attribute]);
            }
        }

        return $result;
    }

    /**
     * Creates an instance of the given class with the given arguments.
     *
     * @template T of Interceptor
     *
     * @param class-string<T> $class The class to create.
     * @param array $arguments The arguments to pass to the constructor.
     *
     * @return T The created instance.
     */
    private function createInstance(string $class, array $arguments = []): Interceptor
    {
        return $this->injector->make($class, $arguments);
    }
}
