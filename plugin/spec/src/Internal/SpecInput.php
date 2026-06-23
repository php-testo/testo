<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;

/**
 * CLI input for specification generation.
 *
 * - `--spec` enables Markdown generation into the plugin's configured directory.
 * - `--spec-dir=<dir>` enables generation and overrides the target directory.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
#[InflectableConfig]
final class SpecInput
{
    /** Enable spec document generation into the default directory. */
    #[InputOption('spec')]
    public bool $spec = false;

    /** Target directory for generated spec documents (`--spec-dir=<dir>`); implies `--spec`. */
    #[InputOption('spec-dir')]
    public ?string $dir = null;

    /**
     * Whether the user asked for spec generation via the CLI.
     */
    public function isEnabled(): bool
    {
        return $this->spec || ($this->dir !== null && $this->dir !== '');
    }

    /**
     * The directory requested on the CLI, or null to fall back to the plugin's configured directory.
     *
     * @return non-empty-string|null
     */
    public function resolveDir(): ?string
    {
        return $this->dir !== null && $this->dir !== '' ? $this->dir : null;
    }
}
