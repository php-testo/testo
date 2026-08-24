<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\CoverageInput;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Report\CoberturaReport;
use Testo\Codecov\Report\PhpUnitXmlReport;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(CoverageInput::class)]
final class CoverageInputTest
{
    public function resolveReportsBuildsRequestedWriters(): void
    {
        $input = new CoverageInput();
        $input->clover = 'build/clover.xml';
        $input->cobertura = 'build/cobertura.xml';
        $input->xml = 'build/coverage-xml';

        $reports = $input->resolveReports();

        Assert::array($reports)->hasCount(3);
        Assert::true($reports[0] instanceof CloverReport);
        Assert::true($reports[1] instanceof CoberturaReport);
        Assert::true($reports[2] instanceof PhpUnitXmlReport);
    }

    public function resolveReportsSkipsUnsetAndEmptyPaths(): void
    {
        $input = new CoverageInput();
        $input->clover = '';
        $input->xml = 'build/coverage-xml';

        $reports = $input->resolveReports();

        Assert::array($reports)->hasCount(1);
        Assert::true($reports[0] instanceof PhpUnitXmlReport);
    }

    public function resolveReportsIsEmptyWithoutFlags(): void
    {
        Assert::array((new CoverageInput())->resolveReports())->hasCount(0);
    }

    public function resolveModeMapsCliFlags(): void
    {
        $always = new CoverageInput();
        $always->coverage = true;
        Assert::same($always->resolveMode(), CoverageMode::Always);

        $never = new CoverageInput();
        $never->noCoverage = true;
        Assert::same($never->resolveMode(), CoverageMode::Never);

        Assert::null((new CoverageInput())->resolveMode());
    }

    public function resolveLevelMapsNamesCaseInsensitively(): void
    {
        Assert::same(self::withLevel('line')->resolveLevel(), CoverageLevel::Line);
        Assert::same(self::withLevel('Branch')->resolveLevel(), CoverageLevel::Branch);
        Assert::same(self::withLevel('PATH')->resolveLevel(), CoverageLevel::Path);
    }

    /**
     * Null means "flag absent", which is what makes the caller fall back to its configured level —
     * an empty `--coverage-level=` must not read as a request for the shallowest one.
     */
    public function resolveLevelIsNullWithoutFlag(): void
    {
        Assert::null((new CoverageInput())->resolveLevel());
        Assert::null(self::withLevel('')->resolveLevel());
    }

    public function resolveLevelRejectsUnknownName(): never
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('quick');

        self::withLevel('quick')->resolveLevel();
    }

    private static function withLevel(string $level): CoverageInput
    {
        $input = new CoverageInput();
        $input->level = $level;

        return $input;
    }
}
