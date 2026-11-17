<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Application\Config\SuiteConfig;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Value\Status;
use Testo\Output\Terminal\ProgramStatus;

/**
 * Turns the run's lifecycle into terminal program status: working with a percent while tests run, then
 * done or error from the run result.
 *
 * The percent is split into segments, each sized as it becomes known. A suite that starts takes an equal
 * share of what is left, shared with the located suites after it, so suites skipped for having no test
 * cases leave their share to the rest: a filtered run that keeps a single suite goes from 0 to 100. A
 * suite's share is split between its test cases once it is scanned, and a case's share between its tests
 * once it starts. Every suite and case start moves the percent to the start of its own segment, so a
 * segment that ran fewer or more tests than counted does not shift the ones after it.
 *
 * @internal
 */
final class RunStatus
{
    /**
     * Positions are integers on this scale: summing float segment widths drifts, and flooring the drift
     * shows 99 for a finished run or steps back a point at a segment boundary.
     */
    private const SCALE = 1_000_000_000;

    /** @var list<non-empty-string> */
    private array $suites = [];

    /**
     * Index of the running suite in {@see $suites}; null while it has no segment.
     */
    private ?int $suite = null;

    /**
     * Index in {@see $suites} the next suite is looked up from, so a repeated name takes its next slot.
     */
    private int $nextSuite = 0;

    private int $suiteStart = 0;
    private int $suiteEnd = 0;

    /** @var list<CaseDefinition> */
    private array $cases = [];

    private bool $inCase = false;
    private int $caseStart = 0;
    private int $caseEnd = 0;
    private int $caseTests = 0;
    private int $caseFinished = 0;
    private string $suiteName = '';

    public function __construct(
        private readonly ProgramStatus $status,
    ) {}

    public function sessionStarted(): void
    {
        $this->status->working();
    }

    /**
     * @param list<SuiteConfig> $suites
     */
    public function suitesLocated(array $suites): void
    {
        $this->suites = \array_map(static fn(SuiteConfig $suite): string => $suite->name, $suites);
        $this->nextSuite = 0;
        $this->suiteEnd = 0;
    }

    public function suiteStarted(SuiteInfo $suite): void
    {
        $this->suiteName = $suite->name;
        $this->cases = $suite->testCases->getCases();
        $this->inCase = false;

        $index = \array_search($suite->name, \array_slice($this->suites, $this->nextSuite, preserve_keys: true), true);
        if ($index === false) {
            # A suite the located list did not announce has no segment to stand on.
            $this->suite = null;
            $this->status->working(message: $this->suiteName);
            return;
        }

        $this->suite = $index;
        $this->nextSuite = $index + 1;
        $this->suiteStart = $this->suiteEnd;
        $this->suiteEnd = $this->suiteStart
            + \intdiv(self::SCALE - $this->suiteStart, \count($this->suites) - $index);
        $this->report($this->suiteStart);
    }

    public function caseStarted(CaseInfo $case): void
    {
        $index = \array_search($case->definition, $this->cases, true);
        $this->inCase = $this->suite !== null && $index !== false;
        if (!$this->inCase) {
            return;
        }

        $width = $this->suiteEnd - $this->suiteStart;
        $count = \count($this->cases);
        $this->caseStart = $this->suiteStart + \intdiv($width * $index, $count);
        $this->caseEnd = $this->suiteStart + \intdiv($width * ($index + 1), $count);
        $this->caseTests = \count($case->definition->tests->getTests());
        $this->caseFinished = 0;
        $this->report($this->caseStart);
    }

    public function testFinished(): void
    {
        if (!$this->inCase || $this->caseTests === 0) {
            return;
        }

        $this->caseFinished = \min($this->caseFinished + 1, $this->caseTests);
        $this->report(
            $this->caseStart + \intdiv(($this->caseEnd - $this->caseStart) * $this->caseFinished, $this->caseTests),
        );
    }

    public function sessionFinished(RunResult $result): void
    {
        $summary = $result->summary;
        $total = $summary->total();
        $skipped = $summary->count(Status::Skipped);
        $failed = $summary->failed() + $summary->count(Status::Aborted);

        match (true) {
            $result->status->isSuccessful() => $this->status->done(
                \sprintf('%d passed', $summary->passed()) . ($skipped > 0 ? \sprintf(', %d skipped', $skipped) : ''),
            ),
            $total === 0 => $this->status->error('No tests executed'),
            $failed === 0 => $this->status->error('Run failed'),
            default => $this->status->error(\sprintf('%d failed of %d', $failed, $total)),
        };
    }

    private function report(int $position): void
    {
        $this->status->working(\intdiv(100 * $position, self::SCALE), $this->suiteName);
    }
}
