<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Testo\Tools\StructArmed\Preset\BridgesPreset;
use Testo\Tools\StructArmed\Preset\CorePreset;
use Testo\Tools\StructArmed\Preset\PluginsPreset;

// One layer, one preset. Each preset owns its own layer's rules — PSR-4,
// encapsulation, and dependency direction alike. Paths inside a preset resolve
// against the project root (analyse runs from there), not this file's location.
return Architecture::define()
    ->withPreset(new CorePreset())
    ->withPreset(new BridgesPreset())
    ->withPreset(new PluginsPreset());
