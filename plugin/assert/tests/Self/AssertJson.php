<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Assert\Internal\Assertion\AssertJson as AssertJsonImpl;
use Testo\Assert\Internal\Assertion\Json\TypeMatcher;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::json()
 */
#[Covers(Assert::class, 'json')]
#[Covers(AssertJsonImpl::class)]
#[Covers(TypeMatcher::class)]
final class AssertJson
{
    public static function types(): iterable
    {
        yield ['false', 'bool'];
        yield ['true', 'bool'];
        yield ['42', 'int'];
        yield ['3.14', 'float'];
        yield ['"hello"', 'string'];
        yield ['"hello world"', 'non-empty-string'];
        yield ['"1234"', 'numeric-string'];
        yield [
            <<<JSON
                {
                    "id": 1,
                    "name": "test"
                }
                JSON,
            'array{id: int, name: non-empty-string}',
        ];
    }

    /**
     * @see \Testo\Assert\Api\Json\JsonCommon::matchesType()
     */
    #[Test]
    #[DataProvider('types')]
    public function matchesType(string $json, string $type): void
    {
        Assert::json($json)->matchesType($type);
    }

    /**
     * One matching JSON value per distinct type arm the matcher supports.
     */
    public static function typeArms(): iterable
    {
        yield 'true literal' => ['true', 'true'];
        yield 'false literal' => ['false', 'false'];
        yield 'mixed accepts anything' => ['[1, 2]', 'mixed'];
        yield 'scalar int' => ['42', 'scalar'];
        yield 'scalar null' => ['null', 'scalar'];
        yield 'numeric int' => ['42', 'numeric'];
        yield 'numeric float' => ['3.14', 'numeric'];
        yield 'numeric string' => ['"42"', 'numeric'];
        yield 'class-string' => ['"AnyClass"', 'class-string'];

        yield 'plain array on object' => ['{"a": 1}', 'array'];
        yield 'plain array on list' => ['[1, 2]', 'array'];
        yield 'plain array empty' => ['{}', 'array'];
        yield 'non-empty-array on object' => ['{"a": 1}', 'non-empty-array'];
        yield 'non-empty-array on list' => ['[1]', 'non-empty-array'];
        yield 'plain list' => ['[1, 2]', 'list'];
        yield 'non-empty plain list' => ['[1, 2]', 'non-empty-list'];

        yield 'generic map key+value' => ['{"a": 1, "b": 2}', 'array<string, int>'];
        yield 'generic array value only' => ['[1, 2]', 'array<int>'];
        yield 'non-empty-list generic' => ['[1, 2]', 'non-empty-list<int>'];
        yield 'non-empty generic map' => ['{"a": 1}', 'non-empty-array<string, int>'];

        yield 'empty shape' => ['{}', 'array{}'];
        yield 'integer shape key on list' => ['[42]', 'array{0: int}'];
        yield 'non-empty shape' => ['{"a": 1}', 'non-empty-array{a: int}'];
    }

    #[Test]
    #[DataProvider('typeArms')]
    public function matchesTypeArms(string $json, string $type): void
    {
        Assert::json($json)->matchesType($type);
    }

    /**
     * @param non-empty-string $json
     * @param non-empty-string $type
     */
    #[Test]
    #[DataSet(['42', 'string'], 'int is not string')]
    #[DataSet(['"hello"', 'int'], 'string is not int')]
    #[DataSet(['{"id": 1}', 'array{id: non-empty-string}'], 'shape value type mismatch')]
    #[DataSet(['true', 'int|string'], 'union all arms fail')]
    #[DataSet(['{"a": 1}', 'list<int>'], 'object is not a list')]
    #[DataSet(['[]', 'non-empty-list<int>'], 'empty non-empty-list')]
    #[DataSet(['["a"]', 'list<int>'], 'list element type mismatch')]
    #[DataSet(['42', 'array<string, int>'], 'scalar is not a map')]
    #[DataSet(['[]', 'non-empty-array<int>'], 'empty non-empty map')]
    #[DataSet(['{"a": 1}', 'array<int, int>'], 'generic key type mismatch')]
    #[DataSet(['[1]', 'array<int, string>'], 'generic value type mismatch')]
    #[DataSet(['42', 'array{id: int}'], 'scalar is not a shape')]
    #[DataSet(['{}', 'non-empty-array{id?: int}'], 'empty non-empty shape')]
    #[DataSet(['{}', 'array{id: int}'], 'shape required key missing')]
    #[DataSet(['42', 'array'], 'scalar is not array')]
    #[DataSet(['{"a": 1}', 'list'], 'object is not a plain list')]
    #[DataSet(['[]', 'non-empty-list'], 'empty non-empty plain list')]
    #[DataSet(['[]', 'non-empty-array'], 'empty array is not non-empty')]
    #[DataSet(['{}', 'non-empty-array'], 'empty object is not non-empty')]
    public function matchesTypeFails(string $json, string $type): never
    {
        Expect::exception(AssertionException::class);
        Assert::json($json)->matchesType($type);
    }

    /**
     * Malformed type expressions are a programmer error: the parser rejects them
     * with an {@see \InvalidArgumentException}, not an assertion failure.
     */
    #[Test]
    #[DataSet(['int)'], 'trailing characters')]
    #[DataSet(['unknown'], 'unknown type name')]
    #[DataSet(['intx'], 'keyword without word boundary')]
    #[DataSet(['array{?: int}'], 'missing shape key')]
    #[DataSet(['int<x, 5>'], 'non-numeric range bound')]
    #[DataSet(['int<0 5>'], 'missing comma in range')]
    public function invalidTypeExpression(string $type): never
    {
        Expect::exception(\InvalidArgumentException::class);
        Assert::json('42')->matchesType($type);
    }

    /**
     * @see \Testo\Assert\Api\Json\JsonStructure
     * @see \Testo\Assert\Api\Json\JsonArray
     * @see \Testo\Assert\Api\Json\JsonObject
     */
    #[Test]
    public function assertStructure(): void
    {
        $json = <<<JSON
            [
                { "id": 1, "name": "Alice" },
                { "id": 2, "name": "Bob" },
                { "id": 3, "name": "Charlie" }
            ]
            JSON;

        # Assert that the JSON is a list of objects with specific structure
        Assert::json($json)
            ->isStructure()
            ->count(3)
            ->matchesType('list<array{id: int<0, max>, name: non-empty-string}>');

        # As an array, assert the first element's structure
        Assert::json($json)
            ->isArray()
            ->count(3)
            ->assertPath('$[0]', static fn(JsonAbstract $json) => $json
                ->isObject()
                ->count(2)
                ->hasKeys(['id', 'name'])
                ->matchesType('array{id: int<0, max>, name: non-empty-string}'));
    }

    #[Test]
    public function invalidJson(): never
    {
        Expect::exception(AssertionException::class);
        Assert::json('{invalid}');
    }

    #[Test]
    public function decode(): void
    {
        Assert::same(Assert::json('42')->decode(), 42);
        Assert::same(Assert::json('"hello"')->decode(), 'hello');
        Assert::same(Assert::json('true')->decode(), true);
        Assert::same(Assert::json('null')->decode(), null);
    }

    #[Test]
    public function isObject(): void
    {
        Assert::json('{"id": 1}')->isObject();
        Assert::json('{}')->isObject();
    }

    /**
     * @param non-empty-string $json
     */
    #[Test]
    #[DataSet(['[1, 2, 3]'], 'array is not object')]
    #[DataSet(['42'], 'primitive is not object')]
    public function isObjectFails(string $json): never
    {
        Expect::exception(AssertionException::class);
        Assert::json($json)->isObject();
    }

    #[Test]
    public function isArray(): void
    {
        Assert::json('[1, 2, 3]')->isArray();
        Assert::json('[]')->isArray();
    }

    /**
     * @param non-empty-string $json
     */
    #[Test]
    #[DataSet(['{"id": 1}'], 'object is not array')]
    #[DataSet(['42'], 'primitive is not array')]
    public function isArrayFails(string $json): never
    {
        Expect::exception(AssertionException::class);
        Assert::json($json)->isArray();
    }

    #[Test]
    public function isPrimitive(): void
    {
        Assert::json('42')->isPrimitive();
        Assert::json('"hello"')->isPrimitive();
        Assert::json('true')->isPrimitive();
        Assert::json('null')->isPrimitive();
        Assert::json('3.14')->isPrimitive();
    }

    /**
     * @param non-empty-string $json
     */
    #[Test]
    #[DataSet(['{"id": 1}'], 'object is not primitive')]
    #[DataSet(['[1, 2, 3]'], 'array is not primitive')]
    public function isPrimitiveFails(string $json): never
    {
        Expect::exception(AssertionException::class);
        Assert::json($json)->isPrimitive();
    }

    #[Test]
    public function emptyStructures(): void
    {
        Assert::json('{}')->empty();
        Assert::json('[]')->empty();
    }

    /**
     * @param non-empty-string $json
     */
    #[Test]
    #[DataSet(['[1, 2, 3]'], 'non-empty array')]
    #[DataSet(['{"a": 1}'], 'non-empty object')]
    #[DataSet(['42'], 'primitive is not a structure')]
    public function emptyStructuresFails(string $json): never
    {
        Expect::exception(AssertionException::class);
        Assert::json($json)->empty();
    }

    #[Test]
    public function countElements(): void
    {
        Assert::json('[1, 2, 3]')->count(3);
        Assert::json('{"a": 1, "b": 2}')->count(2);
        Assert::json('[]')->count(0);
    }

    #[Test]
    public function countElementsFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::json('[1, 2, 3]')->count(5, 'my wonderful message');
    }

    #[Test]
    public function hasKeys(): void
    {
        Assert::json('{"id": 1, "name": "test", "email": "a@b.com"}')
            ->hasKeys(['id', 'name'])
            ->hasKeys('email');
    }

    #[Test]
    public function hasKeysFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::json('{"id": 1}')->hasKeys(['id', 'missing'], 'my wonderful message');
    }

    #[Test]
    public function maxDepth(): void
    {
        Assert::json('42')->maxDepth(1);
        Assert::json('[1, 2, 3]')->maxDepth(1);
        Assert::json('{"a": {"b": 1}}')->maxDepth(2);
        Assert::json('{"a": {"b": {"c": 1}}}')->maxDepth(3);
    }

    #[Test]
    public function maxDepthFails(): never
    {
        Expect::exception(AssertionException::class);
        Assert::json('{"a": {"b": {"c": 1}}}')->maxDepth(2);
    }

    #[Test]
    public function assertPathDotNotation(): void
    {
        $json = '{"user": {"name": "Alice", "age": 30}}';

        Assert::json($json)
            ->assertPath('$.user', static fn(JsonAbstract $json) => $json
                ->isObject()
                ->hasKeys(['name', 'age']));

        Assert::json($json)
            ->assertPath('$.user.name', static fn(JsonAbstract $json) => $json
                ->isPrimitive()
                ->matchesType('non-empty-string'));
    }

    #[Test]
    public function assertPathFails(): never
    {
        Expect::exception(AssertionException::class);
        Assert::json('{"user": {"name": "Alice"}}')
            ->assertPath('$.user.missing', static fn(JsonAbstract $json) => $json->isPrimitive());
    }

    #[Test]
    public function matchesTypeUnion(): void
    {
        Assert::json('42')->matchesType('int|string');
        Assert::json('"hello"')->matchesType('int|string');
        Assert::json('null')->matchesType('int|null');
    }

    #[Test]
    public function matchesTypeOptionalShape(): void
    {
        Assert::json('{"id": 1}')
            ->matchesType('array{id: int, name?: string}');

        Assert::json('{"id": 1, "name": "test"}')
            ->matchesType('array{id: int, name?: string}');
    }

    /**
     * Schema validation is an unimplemented stub: it raises a plain
     * {@see \LogicException}, not an assertion failure.
     */
    #[Test]
    public function matchesSchemaNotImplemented(): never
    {
        Expect::exception(\LogicException::class)
            ->withMessageContaining('Not implemented yet');
        Assert::json('{}')->matchesSchema('{"type": "object"}');
    }

    /**
     * An empty key list is a vacuous requirement: the call is a no-op that
     * stays chainable.
     */
    #[Test]
    public function hasKeysEmptyIsNoop(): void
    {
        Assert::json('{"id": 1}')
            ->hasKeys([])
            ->hasKeys('id');
    }

    /**
     * Keys can be checked against the numeric indices of a JSON array.
     */
    #[Test]
    public function hasKeysOnArray(): void
    {
        Assert::json('["x", "y"]')->hasKeys(['0', '1']);
    }

    /**
     * A primitive has no keys, so any requested key is reported missing.
     */
    #[Test]
    public function hasKeysOnPrimitiveFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('missing key');
        Assert::json('42')->hasKeys('id');
    }

    #[Test]
    public function assertPathUnclosedBracket(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('unclosed bracket in path');
        Assert::json('[1, 2, 3]')
            ->assertPath('$[0', static fn(JsonAbstract $json) => $json->isPrimitive());
    }

    /**
     * Bracketed keys may be quoted with single or double quotes; the quotes
     * are stripped before lookup, allowing keys with spaces.
     */
    #[Test]
    public function assertPathQuotedKey(): void
    {
        $json = '{"first name": "Alice"}';

        Assert::json($json)
            ->assertPath("$['first name']", static fn(JsonAbstract $json) => $json
                ->matchesType('non-empty-string'));

        Assert::json($json)
            ->assertPath('$["first name"]', static fn(JsonAbstract $json) => $json
                ->isPrimitive());
    }

    #[Test]
    public function assertPathEmptyProperty(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('empty property name in path');
        Assert::json('{"a": 1}')
            ->assertPath('$.', static fn(JsonAbstract $json) => $json->isPrimitive());
    }

    #[Test]
    public function assertPathUnexpectedCharacter(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining("unexpected character 'x' in path");
        Assert::json('{"a": 1}')
            ->assertPath('$x', static fn(JsonAbstract $json) => $json->isPrimitive());
    }

    #[Test]
    public function assertPathArrayIndexMissing(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('not found in array');
        Assert::json('[1, 2, 3]')
            ->assertPath('$[5]', static fn(JsonAbstract $json) => $json->isPrimitive());
    }

    /**
     * Navigating a property off a primitive value is a path error, not a
     * silent null.
     */
    #[Test]
    public function assertPathIntoPrimitive(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('cannot access property on int');
        Assert::json('{"a": 1}')
            ->assertPath('$.a.b', static fn(JsonAbstract $json) => $json->isPrimitive());
    }
}
