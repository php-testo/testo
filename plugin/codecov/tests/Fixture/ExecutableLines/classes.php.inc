<?php
namespace App;

use Stringable;

#[\Attribute]
final class Holder implements Stringable
{
    public const A = 1;
    private array $items = [];
    public static int $count = 0;

    public function __construct(
        public readonly string $name = 'x',
        private int $size = 0,
    ) {
        self::$count++;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public static function make(): static
    {
        return new static();
    }

    public function anon(): object
    {
        return new class {
            public function hello(): string
            {
                return 'hi';
            }
        };
    }
}

trait Greets
{
    public function greet(): string
    {
        return 'hello';
    }
}

enum Suit: string
{
    case Hearts = 'H';

    public function label(): string
    {
        return \ucfirst($this->name);
    }
}

interface Contract
{
    public const VERSION = 1;

    public function run(): void;
}
