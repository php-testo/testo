<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Internal\Path;
use Testo\Bridge\Rector\Set\TestoRectorSetList;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\CoversNothing;
use Testo\Data\DataProvider;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\RecordingMessenger;

/**
 * Runs the `testo-shift` set on the fixtures kept next to its config file, in `testo-shift/`.
 * The set configures Rector's own rules, so it has no rule class to carry the fixtures.
 */
#[CoversNothing]
final class TestoShiftSetTest
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function fixtures(): iterable
    {
        $files = \glob(\dirname(TestoRectorSetList::TESTO_SHIFT) . '/testo-shift/*.php.inc') ?: [];
        foreach ($files as $file) {
            yield \basename($file) => [$file];
        }
    }

    #[Test]
    #[DataProvider('fixtures')]
    public function fixture(string $file): void
    {
        $runner = new RectorRunner(new RecordingMessenger(), [], [TestoRectorSetList::TESTO_SHIFT]);
        $runner->assertConverts(Path::create($file));
    }
}
