<?php

declare(strict_types=1);

namespace Testo\Application\Config;

use Internal\Path;
use Testo\Application\Config\Plugin\ApplicationPlugins;
use Testo\Common\PluginConfigurator;

/**
 * Testo application configuration.
 *
 * @api
 */
final readonly class ApplicationConfig
{
    /**
     * Source code location.
     */
    public FinderConfig $src;

    /**
     * @param FinderConfig|list<non-empty-string|Path> $src Source code location.
     *        An array is a shortcut for `new FinderConfig(include: $src)`.
     * @param non-empty-list<SuiteConfig> $suites Specify one or more Test Suites to be executed.
     * @param iterable<PluginConfigurator> $plugins List of plugins to load at the application level (applied to all suites).
     *        An array of plugin instances is treated as {@see ApplicationPlugins::with()}.
     *        Use {@see ApplicationPlugins} facade for advanced configuration.
     * @see DefaultServicesConfig for default services bindings, which can be overridden.
     */
    public function __construct(
        FinderConfig|array $src = [],
        public array $suites = [
            new SuiteConfig(
                name: 'default',
                location: new FinderConfig(['tests']),
            ),
        ],
        public iterable $plugins = [],
    ) {
        $this->src = $src instanceof FinderConfig
            ? $src
            : new FinderConfig(include: $src);

        # Validate suite configs
        $suites === [] and throw new \InvalidArgumentException('At least one test suite must be defined.');
        \array_walk($suites, static fn(mixed $suite): bool => $suite instanceof SuiteConfig
            or throw new \InvalidArgumentException(
                'Each suite must be an instance of SuiteConfig.',
            ));
    }
}
