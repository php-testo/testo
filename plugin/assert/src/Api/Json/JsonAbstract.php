<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Json;

/**
 * Assertion methods for unified JSON data types.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface JsonAbstract extends JsonArray, JsonObject
{
    /**
     * Asserts that the JSON string has a maximum depth.
     *
     * @param int<1, max> $expected The expected maximum depth.
     */
    public function maxDepth(int $expected): static;

    /**
     * Asserts that the JSON string represents a valid JSON structure (object or array).
     */
    public function isStructure(): JsonStructure;

    /**
     * Asserts that the JSON string represents a valid JSON object.
     */
    public function isObject(): JsonObject;

    /**
     * Asserts that the JSON string represents a valid JSON array.
     */
    public function isArray(): JsonArray;

    /**
     * Asserts that the JSON string represents a primitive value (string, number, boolean, null).
     */
    public function isPrimitive(): JsonCommon;

    /**
     * Assert that the JSON array or object is empty.
     */
    public function empty(): JsonCommon;
}
