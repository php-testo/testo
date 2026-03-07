<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Assert\Api\Json\JsonArray;
use Testo\Assert\Api\Json\JsonCommon;
use Testo\Assert\Api\Json\JsonObject;
use Testo\Assert\Api\Json\JsonStructure;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Attribute\AssertMethod;

/**
 * Implementation of JSON assertions.
 *
 * @internal
 */
class AssertJson implements JsonAbstract
{
    public function __construct(
        private mixed $value,
        private readonly AssertionComposite $parent,
    ) {}

    /**
     * Assert that the JSON string has a maximum depth.
     *
     * @param int<1, max> $expected The expected maximum depth.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function maxDepth(int $expected): static
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON array or object is empty.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function empty(): JsonCommon
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the specified path in the JSON structure meets the conditions defined in the callback.
     *
     * @param non-empty-string $path The JSON path to assert.
     * @param callable(JsonAbstract): mixed $callback A callback function that receives an AssertJson
     *        for the value at the specified path.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function assertPath(string $path, callable $callback): static
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the count of the given value matches the expected count.
     *
     * @param int<0, max> $count The expected count.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function count(int $count, string $message = ''): static
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Asserts that the JSON string represents a valid JSON structure (object or array).
     */
    #[AssertMethod]
    #[\Override]
    public function isStructure(): JsonStructure
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON string represents a valid JSON object.
     */
    #[AssertMethod]
    #[\Override]
    public function isObject(): JsonObject
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON string represents a valid JSON array.
     */
    #[AssertMethod]
    #[\Override]
    public function isArray(): JsonArray
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON string represents a primitive value (string, number, boolean, null).
     */
    #[AssertMethod]
    #[\Override]
    public function isPrimitive(): JsonCommon
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Asserts that the JSON structure matches the specified Psalm type.
     *
     * This method validates the decoded JSON against an extended type annotation,
     * enabling precise structure validation with static analysis types.
     *
     * @param non-empty-string $type The Psalm type to validate against
     */
    #[AssertMethod]
    #[\Override]
    public function matchesType(string $type): static
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON structure matches the specified JSON schema.
     *
     * @param non-empty-string $schema The JSON schema to validate against.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function matchesSchema(string $schema): static
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Assert that the JSON object has the specified keys.
     *
     * @param array<string>|string $keys The keys to check for existence.
     */
    #[AssertMethod]
    #[\Override]
    public function hasKeys(array|string $keys, string $message = ''): JsonObject
    {
        throw new \LogicException('Not implemented yet');
    }

    /**
     * Get the decoded JSON value.
     *
     * @return mixed The decoded JSON value.
     *
     * @deprecated To be implemented
     */
    #[AssertMethod]
    #[\Override]
    public function decode(): mixed
    {
        throw new \LogicException('Not implemented yet');
    }
}
