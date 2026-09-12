<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Fixture;

/**
 * A concrete class whose {@see self::greet()} calls a sibling method on `$this`.
 * Under a passthru double the real `greet()` body runs, so its self-call to
 * `normalize()` re-enters the double and hits whatever stub is set for it.
 *
 * Not `final`: Double subclasses the target to build the passthru double.
 */
class Greeter
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $this->normalize($name);
    }

    public function normalize(string $name): string
    {
        return $name;
    }
}
