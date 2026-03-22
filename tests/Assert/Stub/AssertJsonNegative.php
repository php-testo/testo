<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Assert;
use Testo\Test;

/**
 * Stubs for negative {@see Assert::json()} scenarios.
 */
final class AssertJsonNegative
{
    #[Test]
    public function invalidJson(): void
    {
        Assert::json('not valid json');
    }

    #[Test]
    public function isObjectOnArray(): void
    {
        Assert::json('[1, 2, 3]')->isObject();
    }

    #[Test]
    public function isArrayOnObject(): void
    {
        Assert::json('{"key": "value"}')->isArray();
    }

    #[Test]
    public function isPrimitiveOnObject(): void
    {
        Assert::json('{"key": "value"}')->isPrimitive();
    }

    #[Test]
    public function emptyOnNonEmpty(): void
    {
        Assert::json('[1, 2, 3]')->empty();
    }

    #[Test]
    public function wrongCount(): void
    {
        Assert::json('[1, 2, 3]')->count(5);
    }

    #[Test]
    public function missingKeys(): void
    {
        Assert::json('{"id": 1}')->hasKeys(['id', 'name', 'email']);
    }

    #[Test]
    public function exceedsMaxDepth(): void
    {
        Assert::json('{"a": {"b": {"c": 1}}}')->maxDepth(2);
    }

    #[Test]
    public function wrongMatchesType(): void
    {
        Assert::json('42')->matchesType('string');
    }

    #[Test]
    public function isStructureOnPrimitive(): void
    {
        Assert::json('42')->isStructure();
    }
}
