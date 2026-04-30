<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Report;

use Testo\Assert;
use Testo\Codecov\Report\PhpUnitXmlReport;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class PhpUnitXmlReportTest
{
    private string $dir = '';

    public function emptyResultProducesIndexOnly(): void
    {
        (new PhpUnitXmlReport($this->dir, '/project'))->generate(new CoverageResult());

        $index = self::loadXml($this->dir . '/index.xml');
        Assert::notSame($index, false);
        Assert::same((string) $index->project['source'], '/project');
        Assert::same((string) $index->project->directory->totals->lines['executed'], '0');
        Assert::same(\count(\glob($this->dir . '/*.xml') ?: []), 1);
    }

    public function singleFileProducesIndexAndPerFileXml(): void
    {
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
                11 => new LineCoverage(11, LineStatus::NotExecuted),
                12 => new LineCoverage(12, LineStatus::Dead),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        $index = self::loadXml($this->dir . '/index.xml');
        Assert::same((string) $index->project['source'], '/project');
        Assert::same((string) $index->project->directory->totals->lines['total'], '2');
        Assert::same((string) $index->project->directory->totals->lines['executed'], '1');

        $file = $index->project->directory->file;
        Assert::same((string) $file['name'], 'Foo.php');
        Assert::same((string) $file['href'], 'src/Foo.php.xml');

        $perFile = self::loadXml($this->dir . '/src/Foo.php.xml');
        Assert::same((string) $perFile->file['name'], 'Foo.php');
        Assert::same((string) $perFile->file['path'], 'src');
        Assert::same((string) $perFile->file->totals->lines['executed'], '1');

        $coverageLines = $perFile->file->coverage->line;
        Assert::count($coverageLines, 1);
        Assert::same((string) $coverageLines[0]['nr'], '10');
        Assert::same((string) $coverageLines[0]->covered['by'], 'Tests\\FooTest::testA');
    }

    public function multipleTestsCoverSameLine(): void
    {
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, [
                    'Tests\\FooTest::testA',
                    'Tests\\FooTest::testB',
                ]),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        $perFile = self::loadXml($this->dir . '/src/Foo.php.xml');
        $covered = $perFile->file->coverage->line->covered;
        Assert::count($covered, 2);
        Assert::same((string) $covered[0]['by'], 'Tests\\FooTest::testA');
        Assert::same((string) $covered[1]['by'], 'Tests\\FooTest::testB');
    }

    public function executedLineWithoutTestMethodsIsSkipped(): void
    {
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        // Infection consumes only `<covered by>` entries; lines without attribution add nothing.
        $perFile = self::loadXml($this->dir . '/src/Foo.php.xml');
        Assert::count($perFile->file->coverage->line, 0);
    }

    public function nestedSourceFilesMirrorDirectoryStructure(): void
    {
        $result = new CoverageResult([
            '/project/src/Domain/Foo.php' => new FileCoverage('/project/src/Domain/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        $index = self::loadXml($this->dir . '/index.xml');
        $href = (string) $index->project->directory->file['href'];
        Assert::same($href, 'src/Domain/Foo.php.xml');
        Assert::true(\file_exists($this->dir . '/src/Domain/Foo.php.xml'));

        $perFile = self::loadXml($this->dir . '/src/Domain/Foo.php.xml');
        Assert::same((string) $perFile->file['path'], 'src/Domain');
    }

    public function fileWithoutExecutableLinesIsSkipped(): void
    {
        $result = new CoverageResult([
            '/project/src/Empty.php' => new FileCoverage('/project/src/Empty.php', [
                1 => new LineCoverage(1, LineStatus::Dead),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        $index = self::loadXml($this->dir . '/index.xml');
        Assert::count($index->project->directory->file, 0);
        Assert::same(\count(\glob($this->dir . '/*.xml') ?: []), 1);
    }

    /**
     * Infection's parser uses XPath with the `p:` prefix bound to this namespace.
     * Without the declaration, queries like `/p:phpunit/p:file` don't match.
     */
    public function namespaceIsDeclared(): void
    {
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        $indexRaw = (string) \file_get_contents($this->dir . '/index.xml');
        $perFileRaw = (string) \file_get_contents($this->dir . '/src/Foo.php.xml');
        Assert::true(\str_contains($indexRaw, 'xmlns="https://schema.phpunit.de/coverage/1.0"'));
        Assert::true(\str_contains($perFileRaw, 'xmlns="https://schema.phpunit.de/coverage/1.0"'));
    }

    public function readsSourceRootFromCoverageResultWhenNotProvided(): void
    {
        $result = (new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]))->withSourceRoot('/project');

        // No explicit sourceRoot — must be picked up from CoverageResult.
        (new PhpUnitXmlReport($this->dir))->generate($result);

        $perFile = self::loadXml($this->dir . '/src/Foo.php.xml');
        Assert::same((string) $perFile->file['path'], 'src');
        Assert::true(\file_exists($this->dir . '/src/Foo.php.xml'));
    }

    public function constructorSourceRootOverridesCoverageResult(): void
    {
        // CoverageResult says one thing, constructor says another → constructor wins.
        $result = (new CoverageResult([
            '/elsewhere/Foo.php' => new FileCoverage('/elsewhere/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]))->withSourceRoot('/wrong');

        (new PhpUnitXmlReport($this->dir, '/elsewhere'))->generate($result);

        $perFile = self::loadXml($this->dir . '/Foo.php.xml');
        Assert::same((string) $perFile->file['name'], 'Foo.php');
    }

    public function fileOutsideSourceRootFlattensToSlug(): void
    {
        $result = new CoverageResult([
            '/elsewhere/Foo.php' => new FileCoverage('/elsewhere/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($this->dir, '/project'))->generate($result);

        // No subdirectory traversal — slugged into a single filename under the output dir.
        $index = self::loadXml($this->dir . '/index.xml');
        $href = (string) $index->project->directory->file['href'];
        Assert::true(!\str_contains($href, '/'));
        Assert::true(\str_ends_with($href, '.xml'));
        Assert::true(\file_exists($this->dir . '/' . $href));
    }

    #[BeforeTest]
    protected function setUpTmpDir(): void
    {
        $this->dir = \sys_get_temp_dir() . '/testo_phpunit_xml_' . \uniqid();
        \mkdir($this->dir, 0o755, true);
    }

    #[AfterTest]
    protected function cleanUpTmpDir(): void
    {
        if ($this->dir === '' || !\is_dir($this->dir)) {
            return;
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $info) {
            $info->isDir() ? \rmdir($info->getPathname()) : \unlink($info->getPathname());
        }
        \rmdir($this->dir);
        $this->dir = '';
    }

    private static function loadXml(string $path): \SimpleXMLElement|false
    {
        // Drop the namespace so `$xml->file` etc. work without `children('p', true)`.
        $raw = \file_get_contents($path);
        Assert::notSame($raw, false);
        $stripped = \preg_replace('# xmlns="[^"]+"#', '', $raw, 1);

        return \simplexml_load_string((string) $stripped);
    }
}
