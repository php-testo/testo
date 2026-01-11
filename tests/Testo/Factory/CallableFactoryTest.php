<?php

declare(strict_types=1);

namespace Tests\Testo\Factory;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Test\Factory\CallableFactory;
use Tests\Fixture\Factory\SimpleTestCase;

final class CallableFactoryTest
{
    #[Test(description: 'Creates instance using custom callable.')]
    public function itCreatesInstanceUsingCustomCallable(): void
    {
        $callable = fn(\ReflectionClass $class, array $args) => $class->newInstanceArgs($args);
        $factory = new CallableFactory($callable);

        $reflection = new \ReflectionClass(SimpleTestCase::class);
        $instance = $factory->create($reflection);

        Assert::object($instance)->instanceOf(SimpleTestCase::class);
    }
}
