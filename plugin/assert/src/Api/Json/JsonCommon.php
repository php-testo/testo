<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Json;

/**
 * Common methods for JSON assertions.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface JsonCommon
{
    /**
     * Asserts that the JSON structure matches the specified Psalm type.
     *
     * This method validates the decoded JSON against an extended type annotation,
     * enabling precise structure validation with static analysis types.
     *
     * @param non-empty-string $type The Psalm type to validate against
     *        (e.g., 'list<array{foo: bool, bar?: non-empty-string}>')
     *
     * ```
     *  Assert::json('{"foo": true, "bar": "test"}')
     *      ->matchesType('array{foo: bool, bar?: non-empty-string}');
     *
     *  Assert::json('[{"id": 1, "name": "test"}]')
     *      ->matchesType('list<array{id: positive-int, name: non-empty-string}>');
     * ```
     */
    public function matchesType(string $type): static;

    /**
     * Assert that the JSON structure matches the specified JSON schema.
     *
     * @param non-empty-string $schema The JSON schema to validate against.
     */
    public function matchesSchema(string $schema): static;

    /**
     * Get the decoded JSON value.
     *
     * @return mixed The decoded JSON value.
     */
    public function decode(): mixed;
}
