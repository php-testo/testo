<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertCallable as AssertCallableImpl;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::callable()
 */
#[Covers(Assert::class, 'callable')]
#[Covers(AssertCallableImpl::class)]
final class AssertCallable
{
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
}
