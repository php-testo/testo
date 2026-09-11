<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Fixture;

/**
 * Declares a real `allows()` method, whose name collides with one of Double's
 * control verbs. Doubling it needs `Double::for(..., override: true)`.
 */
interface Permissions
{
    public function allows(string $ability): bool;
}
