<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

/**
 * The framework plugins, each a path package under plugin/*.
 */
final readonly class PluginsPreset extends AbstractLayerPreset
{
    protected function layer(): string
    {
        return 'Plugins';
    }

    protected function sourcePaths(): array
    {
        return $this->glob('plugin/*/src');
    }

    protected function mayNotDependOn(): array
    {
        return ['Bridges'];
    }
}
