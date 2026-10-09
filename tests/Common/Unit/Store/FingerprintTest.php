<?php

declare(strict_types=1);

namespace Tests\Common\Unit\Store;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Store\Fingerprint\Extensions;
use Testo\Common\Store\Fingerprint\FileHash;
use Testo\Common\Store\Fingerprint\PhpVersion;
use Testo\Test;

final class FingerprintTest
{
    #[Test]
    #[Covers(PhpVersion::class)]
    public function phpVersionIsMajorMinorOnly(): void
    {
        $contributor = new PhpVersion();

        Assert::same($contributor->key(), 'php');
        Assert::same($contributor->value(), \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION);
    }

    #[Test]
    #[Covers(FileHash::class)]
    public function fileHashReportsAbsenceOfAMissingFile(): void
    {
        $contributor = new FileHash(\sys_get_temp_dir() . '/testo-does-not-exist-' . \bin2hex(\random_bytes(6)));

        Assert::same($contributor->value(), 'absent');
    }

    #[Test]
    #[Covers(FileHash::class)]
    public function fileHashChangesWithContentAndKeysOnThePath(): void
    {
        $file = \sys_get_temp_dir() . '/testo-filehash-' . \bin2hex(\random_bytes(6));
        try {
            \file_put_contents($file, 'one');
            $contributor = new FileHash($file);
            $first = $contributor->value();

            \file_put_contents($file, 'two');
            $second = $contributor->value();

            Assert::same($contributor->key(), (string) Path::create($file));
            Assert::notSame($first, $second);
        } finally {
            @\unlink($file);
        }
    }

    #[Test]
    #[Covers(Extensions::class)]
    public function extensionsAreOrderInsensitiveAndMarkMissingOnes(): void
    {
        $missing = 'testo_missing_ext_' . \bin2hex(\random_bytes(4));

        $a = new Extensions($missing, 'json');
        $b = new Extensions('json', $missing);

        Assert::same($a->key(), 'ext');
        Assert::same($a->value(), $b->value());
        Assert::string($a->value())->contains($missing . ':-');
    }
}
