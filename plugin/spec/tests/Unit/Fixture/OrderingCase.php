<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Fixture;

use Testo\Spec;
use Testo\Spec\SpecHeader;

/**
 * Methods are declared out of number order to prove the interceptor reorders by spec number.
 */
#[SpecHeader('3')]
final class OrderingCase
{
    #[Spec(story: 's')]
    #[SpecHeader('3.3', 'Third')]
    public function third(): void {}

    #[Spec(story: 's')]
    #[SpecHeader('3.1', 'First')]
    public function first(): void {}

    #[Spec(story: 's')]
    #[SpecHeader('3.2', 'Second')]
    public function second(): void {}
}
