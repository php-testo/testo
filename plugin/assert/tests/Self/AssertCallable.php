<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertCallable as AssertCallableImpl;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;
use Tests\Assert\Fixture\CallableSubject;
use Tests\Assert\Fixture\DnfReturnType;

/**
 * @see Assert::callable()
 */
#[Covers(Assert::class, 'callable')]
#[Covers(AssertCallableImpl::class)]
final class AssertCallable
{
    private const SUBJECT = 'Tests\Assert\Fixture\CallableSubject';

    public static function callables(): iterable
    {
        yield 'closure' => [static fn(): int => 42];
        yield 'first-class callable' => [\strlen(...)];
        yield 'function name' => ['strlen'];
        yield 'static method array' => [[\DateTimeImmutable::class, 'createFromFormat']];
        yield 'instance method array' => [[new \ArrayObject(), 'count']];
        yield 'invokable object' => [new class {
            public function __invoke(): void {}
        }];
    }

    public static function nonCallables(): iterable
    {
        yield 'unknown function name' => [
            'no_such_function_for_testo',
            'Failed assertion that `"no_such_function_for_testo"` is callable: got "no_such_function_for_testo".',
        ];
        yield 'private method' => [
            [
                new class {
                    private function hidden(): void {}
                },
                'hidden',
            ],
            'Failed assertion that `array(2)` is callable: got array(2).',
        ];
        yield 'plain object' => [
            new \stdClass(),
            'Failed assertion that `stdClass` is callable: got stdClass.',
        ];
        yield 'int' => [42, 'Failed assertion that `42` is callable: got 42.'];
    }

    public static function staticCallables(): iterable
    {
        yield 'static closure' => [static fn(): int => 1];
        yield 'function name' => ['strlen'];
        yield 'first-class callable of a function' => [\strlen(...)];
        yield 'static method array' => [[CallableSubject::class, 'staticMethod']];
        yield 'static method string' => [self::SUBJECT . '::staticMethod'];
        yield 'first-class callable of a static method' => [CallableSubject::staticMethod(...)];
    }

    /**
     * Callables with the expected failure reason of `isStatic()`.
     */
    public static function nonStaticCallables(): iterable
    {
        yield 'closure not declared static' => [(new CallableSubject())->boundClosure(), 'the closure is not static'];
        yield 'instance method array' => [
            [new CallableSubject(), 'instanceMethod'],
            '`' . self::SUBJECT . '::instanceMethod()` is not static',
        ];
        yield 'first-class callable of an instance method' => [
            (new CallableSubject())->instanceMethod(...),
            '`' . self::SUBJECT . '::instanceMethod()` is not static',
        ];
        yield 'invokable object' => [new CallableSubject(), '`' . self::SUBJECT . '::__invoke()` is not static'];
        yield 'method served by __call()' => [[new CallableSubject(), 'magic'], '`' . self::SUBJECT . '::magic()` is not static'];
    }

    public static function untypedCallables(): iterable
    {
        yield 'untyped method' => [[new CallableSubject(), 'untyped'], '`' . self::SUBJECT . '::untyped()`'];
        yield 'untyped closure' => [static fn() => null, 'the closure'];
    }

    #[Test]
    #[DataProvider('callables')]
    public function callablePasses(mixed $value): void
    {
        Assert::callable($value);
    }

    #[Test]
    #[DataProvider('nonCallables')]
    public function nonCallableFails(mixed $value, string $message): never
    {
        Expect::exception(AssertionException::class)
            ->withMessage($message);
        Assert::callable($value);
    }

    #[Test]
    #[DataProvider('staticCallables')]
    public function isStaticPasses(callable $value): void
    {
        Assert::callable($value)->isStatic();
    }

    #[Test]
    #[DataProvider('staticCallables')]
    public function notStaticFails(callable $value): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageMatchingRegex('/ is not static: .+ is static\.$/');
        Assert::callable($value)->notStatic();
    }

    #[Test]
    #[DataProvider('nonStaticCallables')]
    public function notStaticPasses(callable $value): void
    {
        Assert::callable($value)->notStatic();
    }

    #[Test]
    #[DataProvider('nonStaticCallables')]
    public function isStaticFails(callable $value, string $reason): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining("is static: {$reason}.");
        Assert::callable($value)->isStatic();
    }

    #[Test]
    public function staticFailureShowsTheValueAsGiven(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessage('Failed assertion that `"strlen"` is not static: `strlen()` is static.' . "\nMeaning: my wonderful message");
        Assert::callable('strlen')->notStatic('my wonderful message');
    }

    #[Test]
    public function hasReturnType(): void
    {
        Assert::callable([CallableSubject::class, 'staticMethod'])
            ->hasReturnType('?string')
            ->hasReturnType('string|null')
            ->hasReturnType(' null | STRING ');
        Assert::callable(CallableSubject::staticMethod(...))->hasReturnType('?string');
        Assert::callable([new CallableSubject(), 'instanceMethod'])->hasReturnType('static');
        Assert::callable([new CallableSubject(), 'intersection'])
            ->hasReturnType('\Countable&\ArrayAccess')
            ->hasReturnType('arrayaccess&countable');
        Assert::callable(new CallableSubject())->hasReturnType('null|int|string');
        Assert::callable(self::SUBJECT . '::staticMethod')->hasReturnType('?string');
        Assert::callable('strlen')->hasReturnType('int');
        Assert::callable(static fn(): bool => true)->hasReturnType('bool');
    }

    #[Test]
    public function hasReturnTypeComparesDnfTypes(): void
    {
        \PHP_VERSION_ID < 80200 and throw new SkipTest('DNF types need PHP 8.2.');

        Assert::callable([new DnfReturnType(), 'dnf'])->hasReturnType('null|(\ArrayAccess&\Countable)');
    }

    #[Test]
    public function hasReturnTypeReadsTentativeTypeOfInternalMethod(): void
    {
        Assert::callable([new \ArrayObject(), 'count'])->hasReturnType('int');
    }

    #[Test]
    public function hasReturnTypeFailsOnDifferentType(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessage('Failed assertion that `array(2)` has return type `string`: `' . self::SUBJECT . '::staticMethod()` declares `?string`.');
        Assert::callable([CallableSubject::class, 'staticMethod'])->hasReturnType('string');
    }

    #[Test]
    public function hasReturnTypeDoesNotResolveSubtypes(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('declares `int`');
        Assert::callable('strlen')->hasReturnType('int|string');
    }

    #[Test]
    #[DataProvider('untypedCallables')]
    public function hasReturnTypeFailsWithoutDeclaredType(callable $value, string $name): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining("has return type `mixed`: {$name} declares no return type.");
        Assert::callable($value)->hasReturnType('mixed');
    }
}
