<?php

declare(strict_types=1);

namespace Tests\Metric\Fixture;

/**
 * A backed enum that never joined the {@see \Testo\Metric\Unit} contract.
 */
enum NotAUnit: string
{
    case Whatever = 'whatever';
}
