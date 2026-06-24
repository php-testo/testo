<?php

declare(strict_types=1);

namespace Testo\Application\Config\Plugin;

use Testo\Common\PluginConfigurator;

/**
 * Immutable plugin collection.
 *
 * Use {@see SuitePlugins} or {@see ApplicationPlugins} facades to create instances with default plugins.
 *
 * @implements \IteratorAggregate<int, PluginConfigurator>
 *
 * @api
 */
final readonly class PluginCollection implements \IteratorAggregate, \Countable
{
    /** @var list<PluginConfigurator> */
    private array $plugins;

    public function __construct(PluginConfigurator ...$plugins)
    {
        $this->plugins = \array_values($plugins);
    }

    /**
     * Add plugins to the collection.
     */
    public function with(PluginConfigurator ...$plugins): self
    {
        return new self(...$this->plugins, ...$plugins);
    }

    /**
     * Remove plugins by class name.
     *
     * @param class-string<\Testo\Common\PluginConfigurator> ...$pluginClasses
     */
    public function without(string ...$pluginClasses): self
    {
        return new self(...\array_filter(
            $this->plugins,
            static fn(PluginConfigurator $p): bool => !\in_array($p::class, $pluginClasses, true),
        ));
    }

    /**
     * @return list<PluginConfigurator>
     */
    public function toArray(): array
    {
        return $this->plugins;
    }

    /**
     * @return \ArrayIterator<int<0,max>, \Testo\Common\PluginConfigurator>
     */
    #[\Override]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->plugins);
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->plugins);
    }
}
