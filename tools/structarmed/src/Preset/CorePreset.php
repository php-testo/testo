<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

/**
 * The framework core: Testo\ => core/ in the root composer.json.
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
}
