<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

#[ReflectionClassAttribute('ownClass')]
class ClassWithTrait
{
    use TraitUsingTrait;
}
