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
 * Runs the `phpunit-to-testo` set on the fixtures kept next to its config file, in `phpunit-to-testo/`.
 * They cover what the per-rule fixtures cannot: one rule seeing a node another rule of the set has
 * already rewritten in the same run.
 */
#[CoversNothing]
final class PhpunitToTestoSetTest
{
    /**
     * @return iterable<non-empty-string, array{non-empty-string}>
     */
    public static function fixtures(): iterable
    {
        $files = \glob(\dirname(TestoRectorSetList::PHPUNIT_TO_TESTO) . '/phpunit-to-testo/*.php.inc') ?: [];
        foreach ($files as $file) {
            yield \basename($file) => [$file];
        }
    }

    #[Test]
    #[DataProvider('fixtures')]
    public function fixture(string $file): void
    {
        $runner = new RectorRunner(new RecordingMessenger(), [], [TestoRectorSetList::PHPUNIT_TO_TESTO]);
        $runner->assertConverts(Path::create($file));
    }
}
