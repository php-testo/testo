<?php

declare(strict_types=1);

namespace Testo\Testing\Attribute;

use Internal\Path;
use Testo\Common\PluginConfigurator;

/**
 * Configure Test Suite for the testing tools.
 *
 * @internal
 * @psalm-internal Testo
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class TestingSuite
{
    /** @var list<class-string<PluginConfigurator>|PluginConfigurator> */
    public array $plugins;

    /**
     * @param non-empty-string|Path $path Stub directory or file path.
     * @param list<class-string<PluginConfigurator>|PluginConfigurator> $plugins Extra plugins to load
     *        for the testing suite, on top of the suite defaults. Useful to exercise a plugin's
     *        runtime behaviour end-to-end (e.g. output capturing).
     * @param array<string, mixed> $options CLI options to emulate (as if passed on the command line),
     *        hydrated into config scopes via their {@see \Testo\Application\Config\Internal\Attribute\InputOption}
     *        bindings. E.g. `['group' => ['db'], 'filter' => ['UserTest']]`.
     * @param array<string, mixed> $arguments CLI arguments to emulate, mapped through
     *        {@see \Testo\Application\Config\Internal\Attribute\InputArgument} bindings.
     * @param array<string, string> $env Environment variables to emulate, mapped through
     *        {@see \Testo\Application\Config\Internal\Attribute\Env} bindings.
     */
    public function __construct(
        public string|Path $path,
        array $plugins = [],
        public array $options = [],
        public array $arguments = [],
        public array $env = [],
    ) {
        $this->plugins = $plugins;
    }
}
