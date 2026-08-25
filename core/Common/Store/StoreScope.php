<?php

declare(strict_types=1);

namespace Testo\Common\Store;

use Testo\Common\Store;

/**
 * Ownership scope of a {@see Store}.
 *
 * @api
 */
enum StoreScope
{
    /**
     * One document shared by the whole run, independent of which suite is active.
     */
    case Application;

    /**
     * One document per suite. Data written while one suite is active is unreachable from another —
     * suites may carry different plugin sets and bootstrap, so their persistent state must not leak.
     */
    case Suite;
}
