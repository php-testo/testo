<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit\Reflection;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;
use Testo\Tokenizer\Exception\ReflectionException;
use Testo\Tokenizer\Reflection\TokenizedArgument;
use Testo\Tokenizer\Reflection\TokenizedInvocation;

#[Test]
#[Covers(TokenizedInvocation::class)]
final class TokenizedInvocationTest
{
    // --- constructor / property storage ---

    public function constructorStoresAllProperties(): void
    {
        $filename  = Path::create('/some/file.php');
        $arg       = new TokenizedArgument(TokenizedArgument::STRING, "'hello'");

        $invocation = new TokenizedInvocation(
            filename:  $filename,
            line:      42,
            class:     'Acme\\Foo',
            operator:  '::',
            name:      'doSomething',
            arguments: [$arg],
            source:    'Acme\\Foo::doSomething("hello")',
            level:     0,
        );

        Assert::same($filename, $invocation->filename);
        Assert::same(42, $invocation->line);
        Assert::same('Acme\\Foo', $invocation->class);
        Assert::same('::', $invocation->operator);
        Assert::same('doSomething', $invocation->name);
        Assert::same([$arg], $invocation->arguments);
        Assert::same('Acme\\Foo::doSomething("hello")', $invocation->source);
        Assert::same(0, $invocation->level);
    }

    // --- isMethod ---

    #[DataSet(['Acme\\Foo', '::', true],  'static method call')]
    #[DataSet(['Acme\\Bar', '->',  true],  'instance method call')]
    #[DataSet(['',          '',    false], 'free function call')]
    public function isMethodReflectsWhetherClassIsSet(
        string $class,
        string $operator,
        bool   $expected,
    ): void {
        $invocation = new TokenizedInvocation(
            filename:  Path::create('/f.php'),
            line:      1,
            class:     $class,
            operator:  $operator,
            name:      'fn',
            arguments: [],
            source:    'fn()',
            level:     0,
        );

        Assert::same($expected, $invocation->isMethod());
    }

    // --- getArgument ---

    public function getArgumentReturnsArgumentAtValidIndex(): void
    {
        $arg0 = new TokenizedArgument(TokenizedArgument::VARIABLE, '$x');
        $arg1 = new TokenizedArgument(TokenizedArgument::CONSTANT, '42');

        $invocation = new TokenizedInvocation(
            filename:  Path::create('/f.php'),
            line:      1,
            class:     '',
            operator:  '',
            name:      'fn',
            arguments: [$arg0, $arg1],
            source:    'fn($x, 42)',
            level:     0,
        );

        Assert::same($arg0, $invocation->getArgument(0));
        Assert::same($arg1, $invocation->getArgument(1));
    }

    public function getArgumentThrowsWhenIndexIsOutOfBounds(): void
    {
        $invocation = new TokenizedInvocation(
            filename:  Path::create('/f.php'),
            line:      1,
            class:     '',
            operator:  '',
            name:      'fn',
            arguments: [],
            source:    'fn()',
            level:     0,
        );

        Expect::exception(ReflectionException::class)
            ->withMessage("No such argument with index '0'");

        $invocation->getArgument(0);
    }

    #[DataSet([1], 'index just past end of single-element array')]
    #[DataSet([5], 'large index on empty arguments')]
    public function getArgumentThrowsForAnyMissingIndex(int $index): void
    {
        $invocation = new TokenizedInvocation(
            filename:  Path::create('/f.php'),
            line:      1,
            class:     '',
            operator:  '',
            name:      'fn',
            arguments: [new TokenizedArgument(TokenizedArgument::VARIABLE, '$a')],
            source:    'fn($a)',
            level:     0,
        );

        Expect::exception(ReflectionException::class)
            ->withMessage(\sprintf("No such argument with index '%d'", $index));

        $invocation->getArgument($index);
    }
}
