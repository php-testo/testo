<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Attribute\Test;
use Testo\Data\DataProvider;

/**
 * @see Assert::json()
 */
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
}
