<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

use Testo\Attribute\Test;

final class TestDefinition
{
    public function __construct(
        public readonly \ReflectionFunctionAbstract $reflection,
    ) {}

    public function getDescription(): ?string
    {
        $attributes = $this->reflection->getAttributes(Test::class);
        if (\count($attributes) === 0) {
            return null;
        }

        /** @var \Testo\Attribute\Test $testAttribute */
        $testAttribute = $attributes[0]->newInstance();
        return $testAttribute->description !== '' ? $testAttribute->description : null;
    }
}
