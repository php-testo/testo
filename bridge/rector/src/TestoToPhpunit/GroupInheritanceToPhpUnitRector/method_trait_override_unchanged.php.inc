<?php

trait MethodTaggedTrait
{
    #[\Testo\Filter\Group('trait-group')]
    public function provided(): void
    {
    }
}

final class TraitMethodOverrideTest
{
    use MethodTaggedTrait;

    public function provided(): void
    {
    }
}
