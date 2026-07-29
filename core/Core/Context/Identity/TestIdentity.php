<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Testo\Core\Context\Identity;
use Testo\Core\Internal\CloneWith;

/**
 * Address of a single test within its case — or of one data set of that test.
 *
 * The data set coordinates are the only part that may be absent, and `null` there means "this is the
 * test itself, not one of its data sets". A data set is addressed by its **index**, not by its key:
 * provider keys are free to repeat (`yield 1 => …` twice is legal), so only the index tells two data
 * sets apart. The key stays a label, and lives on the events that carry it.
 *
 * @api
 */
final readonly class TestIdentity extends Identity
{
    use CloneWith;

    /**
     * @param non-empty-string $suite Suite name as configured in `testo.php`.
     * @param non-empty-string $case Class FQN, or the file for a function-based case.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}.
     * @param non-empty-string $test Method or function name.
     * @param int<0, max>|null $provider Index of the data provider a data set came from. Always the
     *        real index — unlike the display-facing one on {@see \Testo\Event\Test\TestDataSetStarting},
     *        which is `null` when the test has a single provider.
     * @param int<0, max>|null $dataSet Index of the data set within that provider.
     */
    public function __construct(
        public string $suite,
        public string $case,
        public string $type,
        public string $test,
        public ?int $provider = null,
        public ?int $dataSet = null,
    ) {
        self::assertDataSetIsWholeOrAbsent($provider, $dataSet);

        parent::__construct();
    }

    /**
     * A more specific address of the same run — a data set of this test.
     *
     * Copies rather than reconstructs, so the derived address stays inside the run it came from: a data
     * set shares its batch's {@see $randomId}, and that is what keeps a batch and its data sets in one
     * report block and one TeamCity flow.
     *
     * @param int<0, max>|null $provider Index of the data provider; keeps the current one when omitted.
     * @param int<0, max>|null $dataSet Index of the data set within it; keeps the current one when omitted.
     */
    public function with(?int $provider = null, ?int $dataSet = null): self
    {
        $provider ??= $this->provider;
        $dataSet ??= $this->dataSet;

        # Re-checked here rather than left to the constructor: copying deliberately bypasses it.
        self::assertDataSetIsWholeOrAbsent($provider, $dataSet);

        /** @see self::$provider, self::$dataSet */
        return $this->cloneWith('provider', $provider)->cloneWith('dataSet', $dataSet);
    }

    #[\Override]
    public function __toString(): string
    {
        $tail = $this->provider === null ? '' : ":{$this->provider}:{$this->dataSet}";

        return "{$this->suite} / {$this->case} [{$this->type}] :: {$this->test}{$tail}";
    }

    private static function assertDataSetIsWholeOrAbsent(?int $provider, ?int $dataSet): void
    {
        ($provider === null) === ($dataSet === null) or throw new \InvalidArgumentException(
            'A data set address needs both a provider index and a data set index, or neither.',
        );
    }
}
