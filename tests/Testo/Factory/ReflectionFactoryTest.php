<?php

declare(strict_types=1);

namespace Tests\Testo\Factory;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Expect;
use Testo\Test\Factory\ReflectionFactory;
use Tests\Fixture\Factory\AbstractTestCase;
use Tests\Fixture\Factory\SimpleTestCase;
use Tests\Fixture\Factory\TestCaseWithConstructor;
use Tests\Fixture\Factory\TestCaseWithPrivateConstructor;

final class ReflectionFactoryTest
{
    private ReflectionFactory $factory;

    public function __construct()
    {
        $this->factory = new ReflectionFactory();
    }

    #[Test(description: 'Creates instance of simple class without constructor.')]
    public function itCreatesInstanceWithoutConstructor(): void
    {
        $reflection = new \ReflectionClass(SimpleTestCase::class);
        $instance = $this->factory->create($reflection);

        Assert::object($instance)->instanceOf(SimpleTestCase::class);
        Assert::same('created', $instance->status);
    }

    #[Test(description: 'Creates instance with constructor arguments.')]
    public function itCreatesInstanceWithConstructorArguments(): void
    {
        $reflection = new \ReflectionClass(TestCaseWithConstructor::class);
        $instance = $this->factory->create($reflection, ['test', 42]);

        Assert::object($instance)->instanceOf(TestCaseWithConstructor::class);
        Assert::same('test', $instance->name);
        Assert::same(42, $instance->value);
    }

    #[Test(description: 'Creates instance without calling constructor when constructor is private.')]
    public function itCreatesInstanceWithoutCallingConstructorWhenConstructorIsPrivate(): void
    {
        $reflection = new \ReflectionClass(TestCaseWithPrivateConstructor::class);
        $instance = $this->factory->create($reflection);

        Assert::object($instance)->instanceOf(TestCaseWithPrivateConstructor::class);
    }

    #[Test(description: 'Throws exception when trying to instantiate abstract class.')]
    public function itThrowsExceptionWhenInstantiatingAbstractClass(): void
    {
        $reflection = new \ReflectionClass(AbstractTestCase::class);

        Expect::exception(\Error::class);
        $this->factory->create($reflection);
    }
}
