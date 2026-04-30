<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Report;

use Testo\Assert;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Codecov\Report\PhpUnitXmlReport;
use Testo\Test;

#[Test]
final class PhpUnitXmlReportTest
{
    public function emptyResultProducesIndexOnly(): void
    {
        $dir = self::tmpDir();

        (new PhpUnitXmlReport($dir, '/project'))->generate(new CoverageResult());

        $index = self::loadXml($dir . '/index.xml');
        Assert::notSame($index, false);
        Assert::same((string) $index->project['source'], '/project');
        Assert::same((string) $index->project->directory->totals->lines['executed'], '0');
        Assert::same(\count(\glob($dir . '/*.xml') ?: []), 1);

        self::cleanup($dir);
    }

    public function singleFileProducesIndexAndPerFileXml(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
                11 => new LineCoverage(11, LineStatus::NotExecuted),
                12 => new LineCoverage(12, LineStatus::Dead),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        // index.xml
        $index = self::loadXml($dir . '/index.xml');
        Assert::same((string) $index->project['source'], '/project');
        Assert::same((string) $index->project->directory->totals->lines['total'], '2');
        Assert::same((string) $index->project->directory->totals->lines['executed'], '1');

        $file = $index->project->directory->file;
        Assert::same((string) $file['name'], 'Foo.php');
        Assert::same((string) $file['href'], 'src/Foo.php.xml');

        // per-file xml
        $perFile = self::loadXml($dir . '/src/Foo.php.xml');
        Assert::same((string) $perFile->file['name'], 'Foo.php');
        Assert::same((string) $perFile->file['path'], 'src');
        Assert::same((string) $perFile->file->totals->lines['executed'], '1');

        $coverageLines = $perFile->file->coverage->line;
        Assert::count($coverageLines, 1);
        Assert::same((string) $coverageLines[0]['nr'], '10');
        Assert::same((string) $coverageLines[0]->covered['by'], 'Tests\\FooTest::testA');

        self::cleanup($dir);
    }

    public function multipleTestsCoverSameLine(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, [
                    'Tests\\FooTest::testA',
                    'Tests\\FooTest::testB',
                ]),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        $perFile = self::loadXml($dir . '/src/Foo.php.xml');
        $covered = $perFile->file->coverage->line->covered;
        Assert::count($covered, 2);
        Assert::same((string) $covered[0]['by'], 'Tests\\FooTest::testA');
        Assert::same((string) $covered[1]['by'], 'Tests\\FooTest::testB');

        self::cleanup($dir);
    }

    public function executedLineWithoutTestMethodsIsSkipped(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        $perFile = self::loadXml($dir . '/src/Foo.php.xml');
        // Coverage element exists but has no <line> children — Infection wants only
        // `<covered by>` entries in the per-line list, lines without attribution add nothing.
        Assert::count($perFile->file->coverage->line, 0);

        self::cleanup($dir);
    }

    public function nestedSourceFilesMirrorDirectoryStructure(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Domain/Foo.php' => new FileCoverage('/project/src/Domain/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        $index = self::loadXml($dir . '/index.xml');
        $href = (string) $index->project->directory->file['href'];
        Assert::same($href, 'src/Domain/Foo.php.xml');
        Assert::true(\file_exists($dir . '/src/Domain/Foo.php.xml'));

        $perFile = self::loadXml($dir . '/src/Domain/Foo.php.xml');
        Assert::same((string) $perFile->file['path'], 'src/Domain');

        self::cleanup($dir);
    }

    public function fileWithoutExecutableLinesIsSkipped(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Empty.php' => new FileCoverage('/project/src/Empty.php', [
                1 => new LineCoverage(1, LineStatus::Dead),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        $index = self::loadXml($dir . '/index.xml');
        Assert::count($index->project->directory->file, 0);
        Assert::same(\count(\glob($dir . '/*.xml') ?: []), 1);

        self::cleanup($dir);
    }

    public function namespaceIsDeclared(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        // Infection's parser uses XPath with the `p:` prefix bound to this namespace.
        // Without the declaration, queries like `/p:phpunit/p:file` don't match.
        $indexRaw = (string) \file_get_contents($dir . '/index.xml');
        $perFileRaw = (string) \file_get_contents($dir . '/src/Foo.php.xml');
        Assert::true(\str_contains($indexRaw, 'xmlns="https://schema.phpunit.de/coverage/1.0"'));
        Assert::true(\str_contains($perFileRaw, 'xmlns="https://schema.phpunit.de/coverage/1.0"'));

        self::cleanup($dir);
    }

    public function readsSourceRootFromCoverageResultWhenNotProvided(): void
    {
        $dir = self::tmpDir();
        $result = (new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]))->withSourceRoot('/project');

        // No explicit sourceRoot — must be picked up from CoverageResult.
        (new PhpUnitXmlReport($dir))->generate($result);

        $perFile = self::loadXml($dir . '/src/Foo.php.xml');
        Assert::same((string) $perFile->file['path'], 'src');
        Assert::true(\file_exists($dir . '/src/Foo.php.xml'));

        self::cleanup($dir);
    }

    public function constructorSourceRootOverridesCoverageResult(): void
    {
        $dir = self::tmpDir();
        // CoverageResult says one thing, constructor says another → constructor wins.
        $result = (new CoverageResult([
            '/elsewhere/Foo.php' => new FileCoverage('/elsewhere/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]))->withSourceRoot('/wrong');

        (new PhpUnitXmlReport($dir, '/elsewhere'))->generate($result);

        $perFile = self::loadXml($dir . '/Foo.php.xml');
        Assert::same((string) $perFile->file['name'], 'Foo.php');

        self::cleanup($dir);
    }

    public function fileOutsideSourceRootFlattensToSlug(): void
    {
        $dir = self::tmpDir();
        $result = new CoverageResult([
            '/elsewhere/Foo.php' => new FileCoverage('/elsewhere/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed, ['Tests\\FooTest::testA']),
            ]),
        ]);

        (new PhpUnitXmlReport($dir, '/project'))->generate($result);

        $index = self::loadXml($dir . '/index.xml');
        $href = (string) $index->project->directory->file['href'];
        // No subdirectory traversal — slugged into a single filename.
        Assert::true(!\str_contains($href, '/'));
        Assert::true(\str_ends_with($href, '.xml'));
        Assert::true(\file_exists($dir . '/' . $href));

        self::cleanup($dir);
    }

    private static function tmpDir(): string
    {
        $dir = \sys_get_temp_dir() . '/testo_phpunit_xml_' . \uniqid();
        \mkdir($dir, 0o755, true);

        return $dir;
    }

    private static function loadXml(string $path): \SimpleXMLElement|false
    {
        // Drop the namespace so `$xml->file` etc. work without `children('p', true)`.
        $raw = \file_get_contents($path);
        Assert::notSame($raw, false);
        $stripped = \preg_replace('# xmlns="[^"]+"#', '', $raw, 1);

        return \simplexml_load_string((string) $stripped);
    }

    private static function cleanup(string $dir): void
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $info) {
            $info->isDir() ? \rmdir($info->getPathname()) : \unlink($info->getPathname());
        }
        \rmdir($dir);
    }
}
