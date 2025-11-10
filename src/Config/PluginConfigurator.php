<?php

declare(strict_types=1);

namespace Testo\Config;

use Testo\Common\Container;

/**
 * Plugin config handler.
 */
interface PluginConfigurator
{
    public function configure(Container $container): void;
}
