<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Application\Config\FinderConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\UnloadedFiles;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Core\Exception\SkipTest;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(UnloadedFiles::class)]
final class UnloadedFilesTest
{
    private const SOURCE = "<?php\nfinal class Foo\n{\n    public function run(): void\n    {\n        \$this->go();\n    }\n}\n";

    private string $root;

    #[BeforeTest]
    public function createRoot(): void
    {
        $this->root = \dirname(__DIR__, 3) . '/tests/runtime/unloaded_files_' . \uniqid();
        \mkdir($this->root . '/src/Excluded', recursive: true);
        $this->root = (string) \realpath($this->root);
    }

    #[AfterTest]
    public function removeRoot(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }
        \rmdir($this->root);
    }

    public function addsUnloadedFileWithEveryLineUnexecuted(): void
    {
        $file = $this->write('src/Foo.php', self::SOURCE);

        $result = UnloadedFiles::add(new CoverageResult(), new FinderConfig([$this->root . '/src']));

        Assert::same(\array_keys($result->files), [$file]);
        Assert::same(\array_keys($result->files[$file]->lines), [6, 7]);
        Assert::same($result->files[$file]->lines[6]->status, LineStatus::NotExecuted);
    }

    public function leavesOutIgnoredLines(): void
    {
        $file = $this->write('src/Foo.php', \str_replace('$this->go();', '$this->go(); // @codeCoverageIgnore', self::SOURCE));

        $result = UnloadedFiles::add(new CoverageResult(), new FinderConfig([$this->root . '/src']));

        Assert::same(\array_keys($result->files[$file]->lines), [7]);
    }

    public function keepsDriverDataOfLoadedFile(): void
    {
        $file = $this->write('src/Foo.php', self::SOURCE);
        $driverPath = \str_replace('/', \DIRECTORY_SEPARATOR, $file);
        $loaded = new CoverageResult([
            $driverPath => new FileCoverage($driverPath, [6 => new LineCoverage(6, LineStatus::Executed)]),
        ]);

        $result = UnloadedFiles::add($loaded, new FinderConfig([$this->root . '/src']));

        Assert::same($result->files, $loaded->files);
    }

    public function skipsExcludedNonPhpAndCodeLessFiles(): void
    {
        $this->write('src/Excluded/Foo.php', self::SOURCE);
        $this->write('src/Skipped.php', self::SOURCE);
        $this->write('src/notes.txt', self::SOURCE);
        $this->write('src/Contract.php', "<?php\ninterface Contract\n{\n    public function run(): void;\n}\n");

        $result = UnloadedFiles::add(
            new CoverageResult(),
            new FinderConfig([$this->root . '/src'], [$this->root . '/src/Excluded', $this->root . '/src/Skipped.php']),
        );

        Assert::same($result->files, []);
    }

    public function skipsLinkLeadingOutOfScope(): void
    {
        \mkdir($this->root . '/outside');
        $target = $this->write('outside/Real.php', self::SOURCE);
        @\symlink($target, $this->root . '/src/Link.php') or throw new SkipTest('Symbolic links are not available.');

        $result = UnloadedFiles::add(new CoverageResult(), new FinderConfig([$this->root . '/src']));

        Assert::same($result->files, []);
    }

    public function emptySourceScopeAddsNothing(): void
    {
        $this->write('src/Foo.php', self::SOURCE);
        $result = new CoverageResult();

        Assert::same(UnloadedFiles::add($result, new FinderConfig()), $result);
    }

    /**
     * @return non-empty-string Real path of the written file.
     */
    private function write(string $relative, string $content): string
    {
        \file_put_contents($this->root . '/' . $relative, $content);

        return (string) \realpath($this->root . '/' . $relative);
    }
}
