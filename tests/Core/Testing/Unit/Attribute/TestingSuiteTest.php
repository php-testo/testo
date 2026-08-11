<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Unit\Attribute;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;

/**
 * Every use of this attribute across the codebase is applied to a class/method/function and read back
 * via {@see \ReflectionAttribute::newInstance()} inside {@see \Testo\Testing\Helper\TestRunner}'s own
 * nested test run — a path Xdebug's coverage collector doesn't see through. A direct construction here
 * exercises the same constructor from a plain, uninstrumented-boundary call.
 */
#[Test]
#[Covers(TestingSuite::class)]
final class TestingSuiteTest
{
    public function constructorStoresAllArgumentsAsPublicProperties(): void
    {
        $attribute = new TestingSuite(
            path: 'some/stub/path',
            plugins: ['SomePlugin'],
            options: ['group' => ['db']],
            arguments: ['name' => 'value'],
            env: ['FOO' => 'bar'],
        );

        Assert::same($attribute->path, 'some/stub/path');
        Assert::same($attribute->plugins, ['SomePlugin']);
        Assert::same($attribute->options, ['group' => ['db']]);
        Assert::same($attribute->arguments, ['name' => 'value']);
        Assert::same($attribute->env, ['FOO' => 'bar']);
    }

    public function constructorDefaultsAreAllEmptyArrays(): void
    {
        $attribute = new TestingSuite(path: 'some/stub/path');

        Assert::same($attribute->plugins, []);
        Assert::same($attribute->options, []);
        Assert::same($attribute->arguments, []);
        Assert::same($attribute->env, []);
    }
}
