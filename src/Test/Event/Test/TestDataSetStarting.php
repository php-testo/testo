<?php

declare(strict_types=1);

namespace Testo\Test\Event\Test;

use Testo\Test\Dto\TestInfo;

/**
 * Event triggered when a DataProvider dataset execution starts.
 *
 * This event fires for each individual dataset run within a DataProvider test.
 * It provides context about which dataset is being executed.
 *
 * @psalm-immutable
 */
final class TestDataSetStarting extends TestEvent
{
    public function __construct(
        TestInfo $testInfo,

        /**
         * The key from the DataProvider (from yield key).
         *
         * @var string|int
         */
        public readonly string|int $dataSetKey,

        /**
         * The zero-based index of this dataset in the sequence.
         *
         * @var int<0, max>
         */
        public readonly int $dataSetIndex,
    ) {
        parent::__construct($testInfo);
    }
}
