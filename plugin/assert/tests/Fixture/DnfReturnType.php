<?php

declare(strict_types=1);

namespace Tests\Assert\Fixture;

/**
 * DNF return type, which parses only on PHP 8.2+: keep it out of files loaded on older versions.
 */
final class DnfReturnType
{
    public function dnf(): (\Countable&\ArrayAccess)|null
    {
        return null;
    }
}
