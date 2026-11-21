<?php

/**
 * Class hierarchy, generators, goto and other edge cases, adapted from the shipmonk-rnd/turbocov
 * test fixtures (MIT).
 */

declare(strict_types=1);

namespace Testo\CoverageProbe\EdgeCases;

interface Shape
{
    public function area(): float;
}

abstract class Base implements Shape
{
    public const UNIT = 'cm';

    protected static int $created = 0;

    abstract public function area(): float;

    public function describe(): string
    {
        return static::class
            . ' '
            . $this->area()
            . self::UNIT;
    }

    public function __destruct()
    {
        self::$created--;
    }
}

enum Kind: string
{
    case Small = 's';
    case Large = 'l';

    public function factor(): int
    {
        return match ($this) {
            self::Small => 1,
            self::Large => 10,
        };
    }
}

final class Square extends Base
{
    public function __construct(private readonly float $side, private Kind $kind = Kind::Small)
    {
        self::$created++;
    }

    public function area(): float
    {
        return $this->side ** 2 * $this->kind->factor();
    }

    public static function counter(): \Closure
    {
        static $calls = 0;

        return static function () use (&$calls): int {
            return ++$calls;
        };
    }

    public function never(): void
    {
        echo 'not called';
    }
}

function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new \DivisionByZeroError(
            'zero',
        );
    }

    return $a / $b;
}

function withFinally(bool $fail): string
{
    $log = [];

    try {
        $log[] = 'try';
        divide(1, $fail ? 0 : 1);
        $log[] = 'after';
    } catch (\DivisionByZeroError) {
        $log[] = 'catch';

        return implode(',', $log);
    } finally {
        $log[] = 'finally';
    }

    return implode(',', $log);
}

function variadic(string $first, int ...$rest): int
{
    $sum = 0;

    foreach ($rest as $value) {
        if ($value < 0) {
            continue;
        }

        if ($value > 100) {
            break;
        }

        $sum += $value;
    }

    return $sum;
}

function shortCircuit(?array $data): bool
{
    return $data !== null
        && isset($data['a'])
        && $data['a'] > 1
        || false;
}

function lazy(): \Generator
{
    $received = yield 1;

    while ($received !== null) {
        $received = yield $received * 2;
    }

    return 'done';
}

function declareConditional(): string
{
    if (!function_exists(__NAMESPACE__ . '\conditional')) {
        function conditional(): string
        {
            return <<<TEXT
                first
                second
                TEXT;
        }
    }

    return conditional();
}

function script(): int
{
    $square = new Square(
        2.0,
        Kind::Large,
    );
    $square->describe();
    $counter = Square::counter();
    $counter();
    $counter();

    withFinally(false);
    withFinally(true);
    variadic('x', 1, -1, 2, 200, 3);
    variadic('x');
    shortCircuit(null);
    shortCircuit(['a' => 5]);

    $generator = lazy();
    $generator->current();
    $generator->send(3);
    $generator->send(null);

    declareConditional();

    $mapped = array_map(
        static fn (int $value): int => $value
            * 2,
        [1, 2],
    );
    usort($mapped, static function (int $a, int $b): int {
        return $b <=> $a;
    });

    [$first, [$second]] = [1, [2]];
    $callable = strlen(...);
    $callable('abc');
    $named = divide(b: 2, a: 4);
    $spread = variadic(...['x', 1, 2]);
    $nullsafe = null;
    $nullsafe?->method()?->other;

    $i = 0;
    loop:
    $i++;
    if ($i < 3) {
        goto loop;
    }

    try {
        divide(1, 0);
    } catch (\Throwable $e) {
        $message = $e->getMessage();
    }

    $anonymous = new class (3) {
        public function __construct(public int $value)
        {
        }

        public function double(): int
        {
            return $this->value * 2;
        }
    };
    $anonymous->double();
    unset($square);

    return $i;
}
