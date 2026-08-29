<?php

declare(strict_types=1);

namespace Testo\Application\Config;

use Internal\Path;

/**
 * What this particular run was told to do: the config file it read and the CLI input it was given.
 *
 * {@see ApplicationConfig} states what the project *can* run — every suite, every plugin. This states
 * what was asked of one invocation, which is not derivable from it: `--filter`, `--group`, `--suite`
 * and `--type` narrow a run without leaving a trace in any result. Reporters need both to describe a
 * run honestly, so the container always holds an instance — an empty one when the application was
 * built from a config object rather than from a command line.
 *
 * Environment variables are deliberately absent. A report is an artifact that gets committed, attached
 * to a CI build and opened by whoever has the link; dumping the environment into it would publish
 * every token the process was handed.
 *
 * @psalm-immutable
 * @api
 */
final readonly class RunConfiguration
{
    /**
     * @param Path|null $configFile Config file the run read, or null when none was found or the
     *        application was built from a config object.
     * @param array<string, mixed> $options CLI options as given, defaults included — the effective
     *        input, not a curated subset. A consumer decides what of it is worth showing.
     * @param array<string, mixed> $arguments CLI arguments as given.
     */
    public function __construct(
        public ?Path $configFile = null,
        public array $options = [],
        public array $arguments = [],
    ) {}

    /**
     * A single option's value, or `$default` when it was never given.
     */
    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Options that carry something, with the empty and the switched-off ones dropped: `false`, `null`,
     * an empty string and an empty array all mean "not asked for" on a Symfony command line, and a
     * reporter listing them would describe defaults as decisions.
     *
     * @return array<string, mixed>
     */
    public function givenOptions(): array
    {
        return \array_filter(
            $this->options,
            static fn(mixed $value): bool => $value !== false && $value !== null
                && $value !== '' && $value !== [],
        );
    }
}
