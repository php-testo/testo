<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

#[ReflectionClassAttribute('outerTrait')]
trait TraitUsingTrait
{
    use InnerTrait;
}
