<?php
final class ClassConstants
{
    private const A = 1;

    public function own(): array
    {
        return [
            self::A,
            ClassConstants::A,
        ];
    }

    public function others(): array
    {
        $user = [
            Elsewhere::A,
            Elsewhere::B,
        ];
        $builtIn = [
            \ReflectionMethod::IS_PUBLIC,
            \ReflectionMethod::IS_STATIC,
        ];
        $names = [
            Elsewhere::class,
            \stdClass::class,
        ];
        $nested = [
            'k' => [Elsewhere::A],
            'l' => 2,
        ];
        return [$user, $builtIn, $names, $nested];
    }

    public static function m($a, $b): int
    {
        return 1;
    }

    public function calls($x, $y): void
    {
        Elsewhere::m(
            $x,
            $y,
        );
        self::m(
            $x,
            $y,
        );
        ClassConstants::m(
            $x,
            $y,
        );
        \DateTime::createFromFormat(
            $x,
            $y,
        );
        \str_repeat(
            $x,
            $y,
        );
        \Elsewhere\fn_call(
            $x,
            $y,
        );
    }
}
