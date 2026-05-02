<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Json;

/**
 * Assertion utilities for JSON object data type.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface JsonObject extends JsonStructure
{
    /**
     * Assert that the JSON object has the specified keys.
     *
     * @param array<string>|string $keys The keys to check for existence.
     */
    public function hasKeys(array|string $keys, string $message = ''): JsonObject;
}
