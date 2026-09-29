<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Exception\CoverageReportNotWritten;
use Testo\Codecov\Internal\ReportFile;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ReportFile::class)]
#[Covers(CoverageReportNotWritten::class)]
final class ReportFileTest
{
    private string $root;

    #[BeforeTest]
    public function createRoot(): void
    {
        $this->root = \dirname(__DIR__, 2) . '/runtime/report_file_' . \uniqid();
        \mkdir($this->root);
    }

    #[AfterTest]
    public function removeRoot(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($this->root);
    }

    public function writesFileCreatingMissingParents(): void
    {
        $path = $this->root . '/a/b/report.xml';

        ReportFile::write($path, '<coverage/>');

        Assert::same(\file_get_contents($path), '<coverage/>');
    }

    public function directoryUnderFileNamesThePath(): never
    {
        \touch($this->root . '/file');
        $dir = $this->root . '/file/reports';

        Expect::exception(CoverageReportNotWritten::class)
            ->withMessageContaining("Unable to create the coverage report directory `{$dir}`: ");

        ReportFile::directory($dir);
    }

    public function fileOverDirectoryNamesThePath(): never
    {
        $path = $this->root . '/report.xml';
        \mkdir($path);

        Expect::exception(CoverageReportNotWritten::class)
            ->withMessageContaining("Unable to write the coverage report file `{$path}`: ");

        ReportFile::write($path, '<coverage/>');
    }

    public function silentFailureStillNamesThePath(): void
    {
        $e = CoverageReportNotWritten::file('/out/report.xml', '');

        Assert::same($e->getMessage(), 'Unable to write the coverage report file `/out/report.xml`.');
    }
}
