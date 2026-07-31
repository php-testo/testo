<?php

declare(strict_types=1);

namespace Testo\Core\Context\Identity;

use Internal\Path;
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
     * The code this address names, without the data. {@see qualifiedName()}
     *
     * @var non-empty-string
     */
    private string $qualifiedName;

    /**
     * Machine-facing form of this address, composed once. {@see fqn()}
     *
     * @var non-empty-string
     */
    private string $fqn;

    /**
     * Rendered form of this address, composed once. {@see __toString()}
     *
     * @var non-empty-string
     */
    private string $display;

    /**
     * @param non-empty-string $suite Suite name as configured in `testo.php`.
     * @param non-empty-string|null $case Class FQN, or null for a free function.
     *        {@see CaseIdentity::$case}.
     * @param non-empty-string $type Case type — `test`, `inline`, `bench`, …
     *        {@see \Testo\Core\Value\TestType}.
     * @param Path $file Path of the file the case was read from.
     *        {@see CaseIdentity::$file}.
     * @param non-empty-string $test Name of the test **relative to `$case`** — a bare method name when
     *        there is a class, and the function's own FQN when there is not. A free function has no
     *        class to be relative to, so it carries its namespace here rather than in a field of its
     *        own; that also makes "a class *and* a namespace" a state this type cannot be put in.
     * @param int<0, max>|null $dataProvider Index of the data provider a data set came from. Always the
     *        real index — unlike the display-facing one on {@see \Testo\Event\Test\TestDataSetStarting},
     *        which is `null` when the test has a single provider.
     * @param int<0, max>|null $dataSet Index of the data set within that provider.
     */
    public function __construct(
        public string $suite,
        public ?string $case,
        public string $type,
        public Path $file,
        public string $test,
        public ?int $dataProvider = null,
        public ?int $dataSet = null,
    ) {
        self::assertDataSetIsWholeOrAbsent($dataProvider, $dataSet);

        # The one place any of these strings is composed. `with()` rebuilds through here rather than
        # copying, so a derived address can never carry a rendering of the coordinates it no longer has.
        $coordinates = $dataProvider === null ? '' : ":{$dataProvider}:{$dataSet}";

        $this->qualifiedName = $case === null ? $test : "{$case}::{$test}";
        $this->fqn = $this->qualifiedName . $coordinates;

        # A case of free functions has no class to name it, so its file stands in.
        $node = $case ?? (string) $file;
        $this->display = "{$suite} / {$node} [{$type}] :: {$test}{$coordinates}";

        parent::__construct();
    }

    /**
     * A more specific address of the same run — a data set of this test.
     *
     * Rebuilt rather than copied, so the constructor recomposes the strings for the new coordinates;
     * the run is then carried over, so the derived address stays inside the one it came from. A data
     * set shares its batch's {@see $randomId}, and that is what keeps a batch and its data sets in one
     * report block and one TeamCity flow.
     *
     * @param int<0, max>|null $dataProvider Index of the data provider; keeps the current one when omitted.
     * @param int<0, max>|null $dataSet Index of the data set within it; keeps the current one when omitted.
     */
    public function with(?int $dataProvider = null, ?int $dataSet = null): self
    {
        $derived = new self(
            $this->suite,
            $this->case,
            $this->type,
            $this->file,
            $this->test,
            $dataProvider ?? $this->dataProvider,
            $dataSet ?? $this->dataSet,
        );

        # Rebuilding minted a fresh run; adopt the one this address came from instead.
        /** @see self::$randomId */
        return $derived->cloneWith('randomId', $this->randomId);
    }

    /**
     * `Namespace\CaseClass::testMethod` — or `Namespace\testFunction` for a free function — plus
     * `:dataProvider:dataSet` when this address points at a data set. The exact string `--filter`
     * takes back, and the tail of TeamCity's `locationHint`, which prefixes `php_qn://` and
     * {@see $file} and leads the qualified name with `\`.
     */
    #[\Override]
    public function fqn(): string
    {
        return $this->fqn;
    }

    /**
     * The code this address names, without the data: `Namespace\CaseClass::testMethod`, or
     * `Namespace\testFunction` for a free function.
     *
     * Every data set of one test answers the same string — which is what consumers that group by test
     * method need. Coverage entries and the JUnit `classname` are keyed on it, and Infection joins them
     * by that name, so per-data-set granularity there would break the lookup rather than sharpen it.
     *
     * @return non-empty-string
     */
    public function qualifiedName(): string
    {
        return $this->qualifiedName;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->display;
    }

    private static function assertDataSetIsWholeOrAbsent(?int $dataProvider, ?int $dataSet): void
    {
        ($dataProvider === null) === ($dataSet === null) or throw new \InvalidArgumentException(
            'A data set address needs both a provider index and a data set index, or neither.',
        );
    }
}
