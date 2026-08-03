<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Messenger;

use Testo\Application\Internal\MessengerHub;

/**
 * Helps {@see MessengerHub} to be readonly to not to be cloned on {@see Container::scope()}.
 *
 * @internal
 */
final class MutableContainer
{
    public function __construct(
        public State $state,
    ) {}
}
