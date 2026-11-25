<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

/**
 * The framework core: Testo\ => core/ in the root composer.json. It is the base
 * every other layer builds on, so it may not reach up into Plugins or into the
 * Bridges integration glue.
 */
final readonly class CorePreset extends AbstractLayerPreset
{
    protected function layer(): string
    {
        return 'Core';
    }

    protected function sourcePaths(): array
    {
        return ['core'];
    }

    protected function mayNotDependOn(): array
    {
        return ['Bridges', 'Plugins'];
    }
}
