<?php

declare(strict_types=1);

namespace Tests\Testo\Interceptor;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Expect;
use Testo\Interceptor\Exception\TestCaseInstantiationException;
use Testo\Interceptor\TestCaseCallInterceptor\InstantiateTestCase;
use Testo\Test\Definition\CaseDefinition;
use Testo\Test\Dto\CaseInfo;
use Testo\Test\Dto\CaseResult;
use Testo\Test\Dto\Status;
use Testo\Test\Factory\ReflectionFactory;
use Testo\Test\TestCaseFactory;
use Tests\Fixture\Factory\AbstractTestCase;
use Tests\Fixture\Factory\SimpleTestCase;
use Tests\Fixture\Factory\TestCaseWithPrivateConstructor;

final class InstantiateTestCaseTest
{
    #[Test(description: 'Instantiates test case when instance is null and reflection exists.')]
    public function itInstantiatesTestCaseWhenInstanceIsNull(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $definition = new CaseDefinition(
            name: 'SimpleTestCase',
            reflection: new \ReflectionClass(SimpleTestCase::class),
        );
        $info = new CaseInfo($definition, instance: null);

        $nextCalled = false;
        $receivedInfo = null;

        $next = function (CaseInfo $i) use (&$nextCalled, &$receivedInfo) {
            $nextCalled = true;
            $receivedInfo = $i;
            return new CaseResult(
                results: [],
                status: Status::Passed,
            );
        };

        $result = $interceptor->runTestCase($info, $next);

        Assert::true($nextCalled);
        Assert::instanceOf(SimpleTestCase::class, $receivedInfo->instance);
        Assert::same(Status::Passed, $result->status);
    }

    #[Test(description: 'Skips instantiation when instance is already set.')]
    public function itSkipsInstantiationWhenInstanceIsAlreadySet(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $existingInstance = new SimpleTestCase();
        $definition = new CaseDefinition(
            name: 'SimpleTestCase',
            reflection: new \ReflectionClass(SimpleTestCase::class),
        );
        $info = new CaseInfo($definition, instance: $existingInstance);

        $receivedInstance = null;
        $next = function (CaseInfo $i) use (&$receivedInstance) {
            $receivedInstance = $i->instance;
            return new CaseResult(
                results: [],
                status: Status::Passed,
            );
        };

        $interceptor->runTestCase($info, $next);

        Assert::same($existingInstance, $receivedInstance);
    }

    #[Test(description: 'Skips instantiation when reflection is null.')]
    public function itSkipsInstantiationWhenReflectionIsNull(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $definition = new CaseDefinition(
            name: 'anonymous',
            reflection: null,
        );
        $info = new CaseInfo($definition, instance: null);

        $receivedInstance = null;
        $next = function (CaseInfo $i) use (&$receivedInstance) {
            $receivedInstance = $i->instance;
            return new CaseResult(
                results: [],
                status: Status::Passed,
            );
        };

        $interceptor->runTestCase($info, $next);

        Assert::null($receivedInstance);
    }

    #[Test(description: 'Wraps factory exceptions in TestCaseInstantiationException.')]
    public function itWrapsFactoryExceptionInTestCaseInstantiationException(): void
    {
        $factory = new class implements TestCaseFactory {
            public function create(\ReflectionClass $class, array $args = []): object
            {
                throw new \RuntimeException('Factory error');
            }
        };

        $interceptor = new InstantiateTestCase($factory);
        $definition = new CaseDefinition(
            name: 'SimpleTestCase',
            reflection: new \ReflectionClass(SimpleTestCase::class),
        );
        $info = new CaseInfo($definition, instance: null);

        $next = fn() => new CaseResult(
            results: [],
            status: Status::Passed,
        );

        Expect::exception(TestCaseInstantiationException::class);

        $interceptor->runTestCase($info, $next);
    }

    #[Test(description: 'Preserves previous exception when wrapping in TestCaseInstantiationException.')]
    public function itPreservesPreviousException(): void
    {
        $originalException = new \InvalidArgumentException('Invalid argument');

        $factory = new class($originalException) implements TestCaseFactory {
            public function __construct(private readonly \Throwable $exception) {}

            public function create(\ReflectionClass $class, array $args = []): object
            {
                throw $this->exception;
            }
        };

        $interceptor = new InstantiateTestCase($factory);
        $definition = new CaseDefinition(
            name: 'SimpleTestCase',
            reflection: new \ReflectionClass(SimpleTestCase::class),
        );
        $info = new CaseInfo($definition, instance: null);

        $next = fn() => new CaseResult(
            results: [],
            status: Status::Passed,
        );

        $caught = false;
        $previous = null;

        try {
            $interceptor->runTestCase($info, $next);
        } catch (TestCaseInstantiationException $e) {
            $caught = true;
            $previous = $e->getPrevious();
        }

        Assert::true($caught);
        Assert::instanceOf(\InvalidArgumentException::class, $previous);
        Assert::same('Invalid argument', $previous->getMessage());
    }

    #[Test(description: 'Throws exception when trying to instantiate abstract class.')]
    public function itThrowsExceptionWhenInstantiatingAbstractClass(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $definition = new CaseDefinition(
            name: 'AbstractTestCase',
            reflection: new \ReflectionClass(AbstractTestCase::class),
        );
        $info = new CaseInfo($definition, instance: null);

        $next = fn() => new CaseResult(
            results: [],
            status: Status::Passed,
        );

        Expect::exception(TestCaseInstantiationException::class);
        $interceptor->runTestCase($info, $next);
    }

    #[Test(description: 'Instantiates class with private constructor using newInstanceWithoutConstructor.')]
    public function itInstantiatesClassWithPrivateConstructorWithoutCallingConstructor(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $definition = new CaseDefinition(
            name: 'TestCaseWithPrivateConstructor',
            reflection: new \ReflectionClass(TestCaseWithPrivateConstructor::class),
        );
        $info = new CaseInfo($definition, instance: null);

        $next = function (CaseInfo $i) {
            Assert::object($i->instance)->instanceOf(TestCaseWithPrivateConstructor::class);
            return new CaseResult(
                results: [],
                status: Status::Passed,
            );
        };

        $interceptor->runTestCase($info, $next);
    }

    #[Test(description: 'Creates new CaseInfo with instance via withInstance method.')]
    public function itCreatesNewCaseInfoWithInstance(): void
    {
        $interceptor = new InstantiateTestCase(new ReflectionFactory());
        $definition = new CaseDefinition(
            name: 'SimpleTestCase',
            reflection: new \ReflectionClass(SimpleTestCase::class),
        );
        $originalInfo = new CaseInfo($definition, instance: null);

        $next = function (CaseInfo $i) use ($originalInfo) {
            Assert::notSame($originalInfo, $i, 'CaseInfo should be a new instance');
            Assert::object($i->instance)->instanceOf(SimpleTestCase::class);
            return new CaseResult(
                results: [],
                status: Status::Passed,
            );
        };

        $interceptor->runTestCase($originalInfo, $next);
    }
}
