<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

/**
 * The framework bridges, each a path package under bridge/*.
 */
final readonly class BridgesPreset extends AbstractLayerPreset
{
    protected function layer(): string
    {
        return 'Bridges';
    }

    protected function sourcePaths(): array
    {
        return $this->glob('bridge/*/src');
    }
}
