<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Json\JsonAbstract;
use Testo\Assert\Api\Json\JsonArray;
use Testo\Assert\Api\Json\JsonCommon;
use Testo\Assert\Api\Json\JsonObject;
use Testo\Assert\Api\Json\JsonStructure;
use Testo\Assert\Internal\Assertion\Json\TypeMatcher;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Common\Attribute\AssertMethod;

/**
 * Implementation of JSON assertions.
 *
 * The value is decoded using {@see json_decode()} without the associative flag,
 * preserving the distinction between JSON objects (stdClass) and arrays.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertJson implements JsonAbstract
{
    private function __construct(
        private mixed $decoded,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is a valid JSON string and create an AssertJson instance.
     *
     * @param string $value The JSON string to validate.
     *
     * @throws AssertionException when the value is not valid JSON.
     */
    public static function validateAndCreate(string $value): self
    {
        try {
            $decoded = \json_decode($value, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            StaticState::typeFail('json', $value);
        }

        $parent = StaticState::typeSuccess('json', $value);
        return new self($decoded, $parent);
    }

    /**
     * Assert that the JSON string has a maximum depth.
     *
     * @param int<1, max> $expected The expected maximum depth.
     */
    #[AssertMethod]
    #[\Override]
    public function maxDepth(int $expected): static
    {
        $actual = $this->calculateDepth($this->decoded);
        $str = "has maximum depth of {$expected}";
        $actual <= $expected
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: "actual depth is {$actual}",
            );

        return $this;
    }

    /**
     * Assert that the JSON array or object is empty.
     */
    #[AssertMethod]
    #[\Override]
    public function empty(): JsonCommon
    {
        $str = 'is empty';

        if (\is_array($this->decoded) && $this->decoded === []) {
            $this->parent->success($str);
            return $this;
        }

        if ($this->decoded instanceof \stdClass && \get_object_vars($this->decoded) === []) {
            $this->parent->success($str);
            return $this;
        }

        $reason = $this->decoded instanceof \stdClass || \is_array($this->decoded)
            ? 'has ' . $this->elementCount() . ' element(s)'
            : 'not a structure';

        throw $this->parent->fail(assertion: $str, reason: $reason);
    }

    /**
     * Assert that the specified path in the JSON structure meets the conditions defined in the callback.
     *
     * @param non-empty-string $path The JSON path (e.g., "$[0]", "$.name", "$[0].name").
     * @param callable(JsonAbstract): mixed $callback A callback that receives an AssertJson
     *        for the value at the specified path.
     */
    #[AssertMethod]
    #[\Override]
    public function assertPath(string $path, callable $callback): static
    {
        $resolved = $this->resolvePath($path);

        $subParent = new AssertionComposite(
            Support::stringify($resolved),
            "at path \"{$path}\"",
            '',
        );
        StaticState::$state === null or StaticState::$state->history[] = $subParent;

        $callback(new self($resolved, $subParent));

        $this->parent->success("path \"{$path}\" assertions pass");
        return $this;
    }

    /**
     * Assert that the count of the given value matches the expected count.
     *
     * @param int<0, max> $count The expected count.
     */
    #[AssertMethod]
    #[\Override]
    public function count(int $count, string $message = ''): static
    {
        $actual = $this->elementCount();
        $str = "has count {$count}";
        $actual === $count
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail(
                assertion: $str,
                reason: "got {$actual}",
                context: $message,
            );

        return $this;
    }

    /**
     * Asserts that the JSON string represents a valid JSON structure (object or array).
     */
    #[AssertMethod]
    #[\Override]
    public function isStructure(): JsonStructure
    {
        $str = 'is a structure (array or object)';
        $this->decoded instanceof \stdClass || \is_array($this->decoded)
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: 'got ' . $this->describeType(),
            );

        return $this;
    }

    /**
     * Assert that the JSON string represents a valid JSON object.
     */
    #[AssertMethod]
    #[\Override]
    public function isObject(): JsonObject
    {
        $str = 'is an object';
        $this->decoded instanceof \stdClass
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: 'got ' . $this->describeType(),
            );

        return $this;
    }

    /**
     * Assert that the JSON string represents a valid JSON array.
     */
    #[AssertMethod]
    #[\Override]
    public function isArray(): JsonArray
    {
        $str = 'is an array';
        \is_array($this->decoded)
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: 'got ' . $this->describeType(),
            );

        return $this;
    }

    /**
     * Assert that the JSON string represents a primitive value (string, number, boolean, null).
     */
    #[AssertMethod]
    #[\Override]
    public function isPrimitive(): JsonCommon
    {
        $str = 'is a primitive';
        !\is_array($this->decoded) && !$this->decoded instanceof \stdClass
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: 'got ' . $this->describeType(),
            );

        return $this;
    }

    /**
     * Asserts that the JSON structure matches the specified Psalm type.
     *
     * @param non-empty-string $type The Psalm type to validate against.
     */
    #[AssertMethod]
    #[\Override]
    public function matchesType(string $type): static
    {
        $str = "matches type \"{$type}\"";
        TypeMatcher::validate($this->decoded, $type)
            ? $this->parent->success($str)
            : throw $this->parent->fail(
                assertion: $str,
                reason: 'got ' . Support::stringify($this->decoded),
            );

        return $this;
    }

    /**
     * Assert that the JSON structure matches the specified JSON schema.
     *
     * @param non-empty-string $schema The JSON schema to validate against.
     *
     * @deprecated To be implemented. Requires a JSON Schema validation library.
     */
    #[AssertMethod]
    #[\Override]
    public function matchesSchema(string $schema): static
    {
        throw new \LogicException('Not implemented yet: requires a JSON Schema validation library.');
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
        $keys = (array) $keys;
        if ($keys === []) {
            return $this;
        }

        $vars = match (true) {
            $this->decoded instanceof \stdClass => \get_object_vars($this->decoded),
            \is_array($this->decoded) => $this->decoded,
            default => [],
        };

        $missingKeys = [];
        $quotedKeys = [];
        foreach ($keys as $key) {
            $quotedKeys[] = "`{$key}`";
            if (!\array_key_exists($key, $vars)) {
                $missingKeys[] = "`{$key}`";
            }
        }

        $m = \count($keys) === 1 ? '' : 's';
        $str = "has key{$m} " . \implode(', ', $quotedKeys);

        if ($missingKeys === []) {
            $this->parent->success($str, $message);
            return $this;
        }

        $m = \count($missingKeys) === 1 ? '' : 's';
        throw $this->parent->fail(
            assertion: $str,
            reason: "missing key{$m}: " . \implode(', ', $missingKeys),
            context: $message,
        );
    }

    /**
     * Get the decoded JSON value.
     *
     * @return mixed The decoded JSON value.
     */
    #[AssertMethod]
    #[\Override]
    public function decode(): mixed
    {
        return $this->decoded;
    }

    /**
     * Describe the JSON type of the decoded value for error messages.
     */
    private function describeType(): string
    {
        return match (true) {
            $this->decoded === null => 'null',
            \is_bool($this->decoded) => 'bool',
            \is_int($this->decoded) => 'int',
            \is_float($this->decoded) => 'float',
            \is_string($this->decoded) => 'string',
            \is_array($this->decoded) => 'array',
            $this->decoded instanceof \stdClass => 'object',
            default => \get_debug_type($this->decoded),
        };
    }

    /**
     * Count elements in the decoded value (array count or object property count).
     */
    private function elementCount(): int
    {
        if ($this->decoded instanceof \stdClass) {
            return \count(\get_object_vars($this->decoded));
        }

        return \is_array($this->decoded) ? \count($this->decoded) : 0;
    }

    /**
     * Calculate the maximum nesting depth of a JSON value.
     * Primitives have depth 1, each array/object nesting adds 1.
     */
    private function calculateDepth(mixed $value, int $current = 1): int
    {
        if ($value instanceof \stdClass) {
            $value = \get_object_vars($value);
        }

        if (!\is_array($value) || $value === []) {
            return $current;
        }

        $max = $current;
        foreach ($value as $item) {
            if (\is_array($item) || $item instanceof \stdClass) {
                $depth = $this->calculateDepth($item, $current + 1);
                if ($depth > $max) {
                    $max = $depth;
                }
            }
        }

        return $max;
    }

    /**
     * Resolve a JSONPath-like expression against the decoded value.
     *
     * Supported syntax:
     * - `$` — root reference (optional prefix, stripped)
     * - `[N]` — array index
     * - `['key']` or `["key"]` — quoted key
     * - `.key` — object/array property
     */
    private function resolvePath(string $path): mixed
    {
        $current = $this->decoded;
        $i = 0;
        $len = \strlen($path);

        // Skip leading $
        if ($i < $len && $path[$i] === '$') {
            $i++;
        }

        while ($i < $len) {
            if ($path[$i] === '[') {
                $i++;
                $end = \strpos($path, ']', $i);
                if ($end === false) {
                    throw $this->parent->fail(
                        assertion: "path \"{$path}\" is valid",
                        reason: 'unclosed bracket in path',
                    );
                }

                $key = \substr($path, $i, $end - $i);
                $i = $end + 1;

                // Remove quotes if present
                if (\strlen($key) >= 2) {
                    $first = $key[0];
                    $last = $key[\strlen($key) - 1];
                    if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                        $key = \substr($key, 1, -1);
                    }
                }

                // Numeric key
                if (\is_string($key) && \ctype_digit($key)) {
                    $key = (int) $key;
                }

                $current = $this->accessKey($current, $key, $path);
            } elseif ($path[$i] === '.') {
                $i++;
                $start = $i;
                while ($i < $len && $path[$i] !== '.' && $path[$i] !== '[') {
                    $i++;
                }
                $key = \substr($path, $start, $i - $start);
                if ($key === '') {
                    throw $this->parent->fail(
                        assertion: "path \"{$path}\" is valid",
                        reason: 'empty property name in path',
                    );
                }

                $current = $this->accessKey($current, $key, $path);
            } else {
                throw $this->parent->fail(
                    assertion: "path \"{$path}\" is valid",
                    reason: "unexpected character '{$path[$i]}' in path",
                );
            }
        }

        return $current;
    }

    /**
     * Access a key on a decoded JSON value (object property or array element).
     */
    private function accessKey(mixed $value, int|string $key, string $path): mixed
    {
        if ($value instanceof \stdClass) {
            $vars = \get_object_vars($value);
            if (\array_key_exists((string) $key, $vars)) {
                return $vars[(string) $key];
            }

            throw $this->parent->fail(
                assertion: "path \"{$path}\" exists",
                reason: "key \"{$key}\" not found in object",
            );
        }

        if (\is_array($value)) {
            if (\array_key_exists($key, $value)) {
                return $value[$key];
            }

            throw $this->parent->fail(
                assertion: "path \"{$path}\" exists",
                reason: "key \"{$key}\" not found in array",
            );
        }

        throw $this->parent->fail(
            assertion: "path \"{$path}\" navigable",
            reason: 'cannot access property on ' . \get_debug_type($value),
        );
    }
}
