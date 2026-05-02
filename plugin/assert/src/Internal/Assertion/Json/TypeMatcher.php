<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion\Json;

/**
 * Validates a PHP value against a Psalm-style type expression.
 *
 * Designed for JSON decoded values (null, bool, int, float, string, array, stdClass).
 * Parses the type expression into an array-based AST, then validates the value against it.
 *
 * Supported type syntax:
 * - Scalar: null, bool, true, false, int, float, string, mixed, scalar, numeric
 * - Extended: non-empty-string, numeric-string, class-string
 * - Int ranges: positive-int, negative-int, non-negative-int, non-positive-int, int<min, max>
 * - Arrays: array, list, non-empty-array, non-empty-list
 * - Generics: array<V>, array<K, V>, list<V>
 * - Shapes: array{key: T, key2?: T}
 * - Unions: T|U
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final class TypeMatcher
{
    private int $pos = 0;
    private readonly int $len;

    /**
     * Validate a value against a Psalm type expression.
     *
     * @param non-empty-string $type Psalm type expression.
     */
    public static function validate(mixed $value, string $type): bool
    {
        $matcher = new self($type);
        $ast = $matcher->parseUnion();

        if ($matcher->pos !== $matcher->len) {
            throw new \InvalidArgumentException(
                \sprintf('Unexpected character at position %d in type: %s', $matcher->pos, $type),
            );
        }

        return self::check($value, $ast);
    }

    private function __construct(
        private readonly string $type,
    ) {
        $this->len = \strlen($type);
    }

    // ---- Parser ----

    /**
     * @return list<mixed> AST node
     */
    private function parseUnion(): array
    {
        $types = [$this->parseAtomic()];

        while ($this->tryConsume('|')) {
            $types[] = $this->parseAtomic();
        }

        return \count($types) === 1 ? $types[0] : ['union', $types];
    }

    /**
     * @return list<mixed> AST node
     */
    private function parseAtomic(): array
    {
        $this->skipWhitespace();

        // Literal types
        if ($this->tryConsume('null')) return ['null'];
        if ($this->tryConsume('true')) return ['true'];
        if ($this->tryConsume('false')) return ['false'];
        if ($this->tryConsume('bool')) return ['bool'];
        if ($this->tryConsume('mixed')) return ['mixed'];
        if ($this->tryConsume('scalar')) return ['scalar'];

        // Numeric types (order: numeric-string before numeric)
        if ($this->tryConsume('numeric-string')) return ['numeric-string'];
        if ($this->tryConsume('numeric')) return ['numeric'];

        // Int types (order: specific ranges before plain int)
        if ($this->tryConsume('positive-int')) return ['int-range', 1, null];
        if ($this->tryConsume('negative-int')) return ['int-range', null, -1];
        if ($this->tryConsume('non-negative-int')) return ['int-range', 0, null];
        if ($this->tryConsume('non-positive-int')) return ['int-range', null, 0];
        if ($this->tryConsume('int')) {
            if ($this->tryConsume('<')) {
                $min = $this->parseBound();
                $this->consume(',');
                $max = $this->parseBound();
                $this->consume('>');
                return ['int-range', $min, $max];
            }
            return ['int'];
        }

        // Float
        if ($this->tryConsume('float')) return ['float'];

        // String types (order: non-empty-string, class-string before string)
        if ($this->tryConsume('non-empty-string')) return ['non-empty-string'];
        if ($this->tryConsume('class-string')) return ['class-string'];
        if ($this->tryConsume('string')) return ['string'];

        // List types (order: non-empty-list before list)
        if ($this->tryConsume('non-empty-list')) {
            if ($this->tryConsume('<')) {
                $valueType = $this->parseUnion();
                $this->consume('>');
                return ['list', $valueType, true];
            }
            return ['plain-list', true];
        }
        if ($this->tryConsume('list')) {
            if ($this->tryConsume('<')) {
                $valueType = $this->parseUnion();
                $this->consume('>');
                return ['list', $valueType, false];
            }
            return ['plain-list', false];
        }

        // Array types (order: non-empty-array before array)
        if ($this->tryConsume('non-empty-array')) {
            return $this->parseArraySuffix(true);
        }
        if ($this->tryConsume('array')) {
            return $this->parseArraySuffix(false);
        }

        throw new \InvalidArgumentException(
            \sprintf('Unknown type at position %d in: %s', $this->pos, $this->type),
        );
    }

    /**
     * Parse array suffix: {shape}, <generic>, or plain.
     *
     * @return list<mixed> AST node
     */
    private function parseArraySuffix(bool $nonEmpty): array
    {
        if ($this->tryConsume('{')) {
            $entries = $this->parseShapeEntries();
            $this->consume('}');
            return ['shape', $entries, $nonEmpty];
        }

        if ($this->tryConsume('<')) {
            $first = $this->parseUnion();
            if ($this->tryConsume(',')) {
                $second = $this->parseUnion();
                $this->consume('>');
                return ['generic-array', $first, $second, $nonEmpty];
            }
            $this->consume('>');
            return ['generic-array', null, $first, $nonEmpty];
        }

        return ['plain-array', $nonEmpty];
    }

    /**
     * @return list<array{string|int, list<mixed>, bool}> Shape entries: [key, type, optional]
     */
    private function parseShapeEntries(): array
    {
        $entries = [];

        while (true) {
            $this->skipWhitespace();
            if ($this->peek() === '}') {
                break;
            }

            $key = $this->parseShapeKey();
            $optional = $this->tryConsume('?');
            $this->consume(':');
            $type = $this->parseUnion();

            $entries[] = [$key, $type, $optional];

            $this->skipWhitespace();
            if (!$this->tryConsume(',')) {
                break;
            }
        }

        return $entries;
    }

    private function parseShapeKey(): string|int
    {
        $this->skipWhitespace();
        $start = $this->pos;

        // Integer key
        if ($this->pos < $this->len && \ctype_digit($this->type[$this->pos])) {
            while ($this->pos < $this->len && \ctype_digit($this->type[$this->pos])) {
                $this->pos++;
            }
            return (int) \substr($this->type, $start, $this->pos - $start);
        }

        // String key (identifier: alphanumeric, underscore, hyphen)
        while (
            $this->pos < $this->len
            && (\ctype_alnum($this->type[$this->pos]) || $this->type[$this->pos] === '_' || $this->type[$this->pos] === '-')
        ) {
            $this->pos++;
        }

        $key = \substr($this->type, $start, $this->pos - $start);
        if ($key === '' || $key === false) {
            throw new \InvalidArgumentException(
                \sprintf('Expected shape key at position %d in: %s', $this->pos, $this->type),
            );
        }

        return $key;
    }

    private function parseBound(): ?int
    {
        $this->skipWhitespace();
        if ($this->tryConsume('min')) return null;
        if ($this->tryConsume('max')) return null;

        $negative = $this->tryConsume('-');
        $start = $this->pos;
        while ($this->pos < $this->len && \ctype_digit($this->type[$this->pos])) {
            $this->pos++;
        }

        if ($this->pos === $start) {
            throw new \InvalidArgumentException(
                \sprintf('Expected bound at position %d in: %s', $this->pos, $this->type),
            );
        }

        $num = (int) \substr($this->type, $start, $this->pos - $start);
        return $negative ? -$num : $num;
    }

    // ---- Lexer helpers ----

    /**
     * Try to consume a keyword or symbol. For alphabetic keywords, checks word boundary.
     */
    private function tryConsume(string $str): bool
    {
        $len = \strlen($str);
        if ($this->pos + $len > $this->len) {
            return false;
        }

        if (\substr_compare($this->type, $str, $this->pos, $len) === 0) {
            // Word boundary check for alphabetic keywords
            if (\ctype_alpha($str[$len - 1])) {
                $nextPos = $this->pos + $len;
                if ($nextPos < $this->len) {
                    $c = $this->type[$nextPos];
                    if (\ctype_alnum($c) || $c === '-' || $c === '_') {
                        return false;
                    }
                }
            }
            $this->pos += $len;
            return true;
        }

        return false;
    }

    /**
     * Consume an expected single character, skipping leading whitespace.
     */
    private function consume(string $char): void
    {
        $this->skipWhitespace();
        if ($this->pos >= $this->len || $this->type[$this->pos] !== $char) {
            throw new \InvalidArgumentException(
                \sprintf("Expected '%s' at position %d in: %s", $char, $this->pos, $this->type),
            );
        }
        $this->pos++;
    }

    private function peek(): ?string
    {
        return $this->pos < $this->len ? $this->type[$this->pos] : null;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && $this->type[$this->pos] === ' ') {
            $this->pos++;
        }
    }

    // ---- Validator ----

    /**
     * @param list<mixed> $node AST node
     */
    private static function check(mixed $value, array $node): bool
    {
        /** @psalm-suppress MixedArgument */
        return match ($node[0]) {
            'null' => $value === null,
            'true' => $value === true,
            'false' => $value === false,
            'bool' => \is_bool($value),
            'mixed' => true,
            'scalar' => \is_scalar($value) || $value === null,
            'numeric' => \is_int($value) || \is_float($value) || (\is_string($value) && \is_numeric($value)),
            'numeric-string' => \is_string($value) && \is_numeric($value),
            'int' => \is_int($value),
            'int-range' => \is_int($value)
                && ($node[1] === null || $value >= $node[1])
                && ($node[2] === null || $value <= $node[2]),
            'float' => \is_float($value),
            'string' => \is_string($value),
            'non-empty-string' => \is_string($value) && $value !== '',
            'class-string' => \is_string($value),
            'union' => self::checkUnion($value, $node[1]),
            'plain-array' => (\is_array($value) || $value instanceof \stdClass)
                && (!$node[1] || self::structureNotEmpty($value)),
            'plain-list' => \is_array($value) && \array_is_list($value) && (!$node[1] || $value !== []),
            'generic-array' => self::checkGenericArray($value, $node[1], $node[2], $node[3]),
            'list' => self::checkList($value, $node[1], $node[2]),
            'shape' => self::checkShape($value, $node[1], $node[2]),
            default => false,
        };
    }

    /**
     * @param list<list<mixed>> $types
     */
    private static function checkUnion(mixed $value, array $types): bool
    {
        foreach ($types as $type) {
            if (self::check($value, $type)) {
                return true;
            }
        }

        return false;
    }

    private static function structureNotEmpty(mixed $value): bool
    {
        if ($value instanceof \stdClass) {
            return \get_object_vars($value) !== [];
        }

        return \is_array($value) && $value !== [];
    }

    /**
     * @param list<mixed>|null $keyType
     * @param list<mixed> $valueType
     */
    private static function checkGenericArray(mixed $value, ?array $keyType, array $valueType, bool $nonEmpty): bool
    {
        if ($value instanceof \stdClass) {
            $entries = \get_object_vars($value);
        } elseif (\is_array($value)) {
            $entries = $value;
        } else {
            return false;
        }

        if ($nonEmpty && $entries === []) {
            return false;
        }

        foreach ($entries as $k => $v) {
            if ($keyType !== null && !self::check($k, $keyType)) {
                return false;
            }
            if (!self::check($v, $valueType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $valueType
     */
    private static function checkList(mixed $value, array $valueType, bool $nonEmpty): bool
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            return false;
        }

        if ($nonEmpty && $value === []) {
            return false;
        }

        foreach ($value as $v) {
            if (!self::check($v, $valueType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{string|int, list<mixed>, bool}> $entries
     */
    private static function checkShape(mixed $value, array $entries, bool $nonEmpty): bool
    {
        if ($value instanceof \stdClass) {
            $data = \get_object_vars($value);
        } elseif (\is_array($value)) {
            $data = $value;
        } else {
            return false;
        }

        if ($nonEmpty && $data === []) {
            return false;
        }

        foreach ($entries as [$key, $type, $optional]) {
            if (!\array_key_exists($key, $data)) {
                if (!$optional) {
                    return false;
                }
                continue;
            }
            if (!self::check($data[$key], $type)) {
                return false;
            }
        }

        return true;
    }
}
