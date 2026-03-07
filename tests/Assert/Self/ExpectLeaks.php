<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert\ExpectException;
use Testo\Assert\State\Expectation\ExpectLeaksFailure;
use Testo\Attribute\Test;
use Testo\Expect;

/**
 * @see Expect::leaks()
 */
final class ExpectLeaks
{
    #[Test]
    public function cachedStatically(): void
    {
        static $leak = null;
        $leak = [
            new \stdClass(),
            new \DateTimeImmutable(),
        ];
        Expect::leaks(...$leak)->message('foo bar');
    }

    #[Test]
    #[ExpectException(ExpectLeaksFailure::class)]
    public function leaks(): void
    {
        $leak = new \stdClass();
        Expect::leaks($leak)->message('foo bar');
    }
}
