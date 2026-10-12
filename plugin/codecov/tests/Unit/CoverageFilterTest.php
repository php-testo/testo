<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\CoverageFilter;
use Testo\Codecov\Result\BranchCoverage;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\FunctionCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Codecov\Result\PathCoverage;
use Testo\Test;
use Tests\Codecov\Stub\TargetClassA;
use Tests\Codecov\Stub\TargetClassB;
use Tests\Codecov\Stub\TargetEnum;
use Tests\Codecov\Stub\TargetTrait;

#[Test]
final class CoverageFilterTest
{
    public function enumCoversAllLines(): void
    {
        $ref = new \ReflectionEnum(TargetEnum::class);
        $file = \str_replace('\\', '/', $ref->getFileName());
        $coverage = self::fullCoverage($file, $ref->getStartLine(), $ref->getEndLine());

        $result = CoverageFilter::apply($coverage, [new Covers(TargetEnum::class)]);

        Assert::true(isset($result->files[$file]));
        Assert::same(
            \count($result->files[$file]->lines),
            \count($coverage->files[$file]->lines),
        );
    }

    public function enumCoversSpecificMethod(): void
    {
        $ref = new \ReflectionMethod(TargetEnum::class, 'label');
        $file = \str_replace('\\', '/', $ref->getFileName());
        $enumRef = new \ReflectionEnum(TargetEnum::class);
        $coverage = self::fullCoverage($file, $enumRef->getStartLine(), $enumRef->getEndLine());

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetEnum::class, 'label'),
        ]);

        Assert::true(isset($result->files[$file]));

        foreach ($result->files[$file]->lines as $line => $lineCoverage) {
            Assert::same(
                $lineCoverage->status,
                $line >= $ref->getStartLine() && $line <= $ref->getEndLine() ? LineStatus::Executed : LineStatus::NotExecuted,
                "Line {$line} is credited only inside method range {$ref->getStartLine()}-{$ref->getEndLine()}",
            );
        }
    }

    public function enumStaticMethodCovered(): void
    {
        $ref = new \ReflectionMethod(TargetEnum::class, 'fromLabel');
        $file = \str_replace('\\', '/', $ref->getFileName());
        $enumRef = new \ReflectionEnum(TargetEnum::class);
        $coverage = self::fullCoverage($file, $enumRef->getStartLine(), $enumRef->getEndLine());

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetEnum::class, 'fromLabel'),
        ]);

        Assert::true(isset($result->files[$file]));
        Assert::true(\count($result->files[$file]->lines) > 0);
    }

    public function traitCoversAllLines(): void
    {
        $ref = new \ReflectionClass(TargetTrait::class);
        $file = \str_replace('\\', '/', $ref->getFileName());
        $coverage = self::fullCoverage($file, $ref->getStartLine(), $ref->getEndLine());

        $result = CoverageFilter::apply($coverage, [new Covers(TargetTrait::class)]);

        Assert::true(isset($result->files[$file]));
        Assert::same(
            \count($result->files[$file]->lines),
            \count($coverage->files[$file]->lines),
        );
    }

    public function traitCoversSpecificMethod(): void
    {
        $ref = new \ReflectionMethod(TargetTrait::class, 'greet');
        $file = \str_replace('\\', '/', $ref->getFileName());
        $traitRef = new \ReflectionClass(TargetTrait::class);
        $coverage = self::fullCoverage($file, $traitRef->getStartLine(), $traitRef->getEndLine());

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetTrait::class, 'greet'),
        ]);

        Assert::true(isset($result->files[$file]));

        foreach ($result->files[$file]->lines as $line => $lineCoverage) {
            Assert::same(
                $lineCoverage->status,
                $line >= $ref->getStartLine() && $line <= $ref->getEndLine() ? LineStatus::Executed : LineStatus::NotExecuted,
                "Line {$line} is credited only inside method range {$ref->getStartLine()}-{$ref->getEndLine()}",
            );
        }
    }

    public function classPrivateMethodCovered(): void
    {
        $ref = new \ReflectionMethod(TargetClassA::class, 'internalHelper');
        $file = \str_replace('\\', '/', $ref->getFileName());
        $classRef = new \ReflectionClass(TargetClassA::class);
        $coverage = self::fullCoverage($file, $classRef->getStartLine(), $classRef->getEndLine());

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetClassA::class, 'internalHelper'),
        ]);

        Assert::true(isset($result->files[$file]));

        foreach ($result->files[$file]->lines as $line => $lineCoverage) {
            Assert::same(
                $lineCoverage->status,
                $line >= $ref->getStartLine() && $line <= $ref->getEndLine() ? LineStatus::Executed : LineStatus::NotExecuted,
                "Line {$line} is credited only inside method range {$ref->getStartLine()}-{$ref->getEndLine()}",
            );
        }
    }

    public function classStillWorks(): void
    {
        $ref = new \ReflectionClass(TargetClassA::class);
        $file = \str_replace('\\', '/', $ref->getFileName());
        $coverage = self::fullCoverage($file, $ref->getStartLine(), $ref->getEndLine());

        $result = CoverageFilter::apply($coverage, [new Covers(TargetClassA::class)]);

        Assert::true(isset($result->files[$file]));
    }

    public function multipleCoversCollectsAllTargets(): void
    {
        // Two classes in different files.
        $refA = new \ReflectionClass(TargetClassA::class);
        $fileA = \str_replace('\\', '/', $refA->getFileName());

        $refB = new \ReflectionClass(TargetClassB::class);
        $fileB = \str_replace('\\', '/', $refB->getFileName());

        $coverage = new CoverageResult([
            $fileA => new FileCoverage($fileA, self::lineRange($refA->getStartLine(), $refA->getEndLine())),
            $fileB => new FileCoverage($fileB, self::lineRange($refB->getStartLine(), $refB->getEndLine())),
        ]);

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetClassA::class),
            new Covers(TargetClassB::class),
        ]);

        // Both files present.
        Assert::true(isset($result->files[$fileA]));
        Assert::true(isset($result->files[$fileB]));
    }

    public function multipleCoversMixedTypes(): void
    {
        // Class, trait, enum across different files.
        $refClass = new \ReflectionClass(TargetClassA::class);
        $fileClass = \str_replace('\\', '/', $refClass->getFileName());

        $refTrait = new \ReflectionClass(TargetTrait::class);
        $fileTrait = \str_replace('\\', '/', $refTrait->getFileName());

        $refEnum = new \ReflectionEnum(TargetEnum::class);
        $fileEnum = \str_replace('\\', '/', $refEnum->getFileName());

        $coverage = new CoverageResult([
            $fileClass => new FileCoverage($fileClass, self::lineRange($refClass->getStartLine(), $refClass->getEndLine())),
            $fileTrait => new FileCoverage($fileTrait, self::lineRange($refTrait->getStartLine(), $refTrait->getEndLine())),
            $fileEnum => new FileCoverage($fileEnum, self::lineRange($refEnum->getStartLine(), $refEnum->getEndLine())),
        ]);

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetClassA::class),
            new Covers(TargetTrait::class),
            new Covers(TargetEnum::class),
        ]);

        // All three files present.
        Assert::true(isset($result->files[$fileClass]));
        Assert::true(isset($result->files[$fileTrait]));
        Assert::true(isset($result->files[$fileEnum]));
    }

    public function multipleCoversMethodsInSameFile(): void
    {
        // Two methods from the same enum.
        $refLabel = new \ReflectionMethod(TargetEnum::class, 'label');
        $refFromLabel = new \ReflectionMethod(TargetEnum::class, 'fromLabel');
        $enumRef = new \ReflectionEnum(TargetEnum::class);
        $file = \str_replace('\\', '/', $enumRef->getFileName());
        $coverage = self::fullCoverage($file, $enumRef->getStartLine(), $enumRef->getEndLine());

        $result = CoverageFilter::apply($coverage, [
            new Covers(TargetEnum::class, 'label'),
            new Covers(TargetEnum::class, 'fromLabel'),
        ]);

        // File present, lines from both methods included.
        Assert::true(isset($result->files[$file]));

        $lines = \array_keys($result->files[$file]->lines);
        $hasLabel = false;
        $hasFromLabel = false;
        foreach ($lines as $line) {
            if ($line >= $refLabel->getStartLine() && $line <= $refLabel->getEndLine()) {
                $hasLabel = true;
            }
            if ($line >= $refFromLabel->getStartLine() && $line <= $refFromLabel->getEndLine()) {
                $hasFromLabel = true;
            }
        }
        Assert::true($hasLabel, 'Expected lines from label() method');
        Assert::true($hasFromLabel, 'Expected lines from fromLabel() method');
    }

    public function emptyTargetsCreditNothing(): void
    {
        $coverage = new CoverageResult(['foo.php' => new FileCoverage('foo.php', [1 => new LineCoverage(1, LineStatus::Executed)])]);

        $result = CoverageFilter::apply($coverage, []);

        Assert::same($result->files['foo.php']->lines[1]->status, LineStatus::NotExecuted);
    }

    public function linesOutsideTargetsKeepTheirDeadStatus(): void
    {
        $coverage = new CoverageResult(['foo.php' => new FileCoverage('foo.php', [
            1 => new LineCoverage(1, LineStatus::Dead),
            2 => new LineCoverage(2, LineStatus::NotExecuted),
        ])]);

        $result = CoverageFilter::apply($coverage, []);

        Assert::same($result->files['foo.php']->lines[1]->status, LineStatus::Dead);
        Assert::same($result->files['foo.php']->lines[2]->status, LineStatus::NotExecuted);
    }

    public function branchesOutsideTargetsLoseTheirHits(): void
    {
        $ref = new \ReflectionMethod(TargetClassA::class, 'internalHelper');
        $file = \str_replace('\\', '/', $ref->getFileName());
        $line = $ref->getStartLine() + 2;
        $function = new FunctionCoverage(
            'TargetClassA->internalHelper',
            [0 => new BranchCoverage(0, 3, $line, $line, true, [5], [true])],
            [new PathCoverage([0], true)],
        );
        $coverage = new CoverageResult([$file => new FileCoverage($file, [], ['TargetClassA->internalHelper' => $function])]);

        $outside = CoverageFilter::apply($coverage, [new Covers(TargetClassA::class, 'doSomething')]);
        $inside = CoverageFilter::apply($coverage, [new Covers(TargetClassA::class, 'internalHelper')]);

        $branch = $outside->files[$file]->functions['TargetClassA->internalHelper']->branches[0];
        Assert::false($branch->hit);
        Assert::same($branch->outHit, [false]);
        Assert::false($outside->files[$file]->functions['TargetClassA->internalHelper']->paths[0]->hit);
        Assert::same($inside->files[$file]->functions['TargetClassA->internalHelper'], $function);
    }

    public function functionWithoutBranchesIsKeptEmpty(): void
    {
        $coverage = new CoverageResult(['foo.php' => new FileCoverage('foo.php', [], ['{main}' => new FunctionCoverage('{main}', [], [])])]);

        $result = CoverageFilter::apply($coverage, []);

        Assert::equals($result->files['foo.php']->functions['{main}'], new FunctionCoverage('{main}', [], []));
    }

    /**
     * Build CoverageResult with all lines marked as Executed for a file range.
     */
    private static function fullCoverage(string $file, int $start, int $end): CoverageResult
    {
        return new CoverageResult([$file => new FileCoverage($file, self::lineRange($start, $end))]);
    }

    /**
     * @return array<int, LineCoverage>
     */
    private static function lineRange(int $start, int $end): array
    {
        $lines = [];
        for ($i = $start; $i <= $end; $i++) {
            $lines[$i] = new LineCoverage($i, LineStatus::Executed);
        }
        return $lines;
    }
}
