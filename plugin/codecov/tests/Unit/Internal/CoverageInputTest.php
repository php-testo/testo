<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\CoverageInput;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Report\CoberturaReport;
use Testo\Codecov\Report\PhpUnitXmlReport;
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
}
