<?php

namespace AttrClosure;

#[\Attribute(\Attribute::TARGET_ALL)]
final class Check
{
    public function __construct(public \Closure $fn) {}
}

#[Check(static function (int $x): bool {
    $y = $x * 2;
    return $y > 0;
})]
final class Subject
{
    public const VALIDATOR = static function (string $s): string {
        return \trim($s);
    };

    #[Check(static function (): void {
        echo 'method attr';
    })]
    public function run(
        \Closure $cb = static function (): int {
            return 1;
        },
    ): void {
        $cb();
    }

    public static function print(mixed $value): string
    {
        return (string) $value;
    }
}

#[Check(static function (int $x): int {
    return $x + 1;
})]
function free(): void {}
