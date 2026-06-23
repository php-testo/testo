<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Fixture;

use Testo\Spec;
use Testo\Spec\SpecHeader;

/**
 * Methods are declared out of number order to prove the interceptor reorders by spec number.
 */
#[SpecHeader(number: '3')]
final class OrderingCase
{
    #[Spec(story: 's')]
    #[SpecHeader(title: 'Third', number: '3.3')]
    public function third(): void {}

    #[Spec(story: 's')]
    #[SpecHeader(title: 'First', number: '3.1')]
    public function first(): void {}

    #[Spec(story: 's')]
    #[SpecHeader(title: 'Second', number: '3.2')]
    public function second(): void {}
}
