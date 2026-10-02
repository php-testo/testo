<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Driver;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\Driver\PseudoFile;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(PseudoFile::class)]
final class PseudoFileTest
{
    private string $root;

    #[BeforeTest]
    public function createRoot(): void
    {
        $this->root = \dirname(__DIR__, 2) . '/runtime/pseudo_file_' . \uniqid();
        \mkdir($this->root, recursive: true);
    }

    #[AfterTest]
    public function removeRoot(): void
    {
        foreach (\glob($this->root . '/*') as $file) {
            \unlink($file);
        }
        \rmdir($this->root);
    }

    public function removeKeepsRealFilesWithTheirData(): void
    {
        $data = [
            __FILE__ => [10 => 1, 11 => -1],
            __FILE__ . "(64) : eval()'d code" => [1 => 1],
            $this->root . '/missing.php' => [1 => 1],
            __DIR__ . '/NormalizePathTest.php' => [20 => -2],
        ];

        $result = PseudoFile::remove($data);

        Assert::same($result, [
            __FILE__ => [10 => 1, 11 => -1],
            __DIR__ . '/NormalizePathTest.php' => [20 => -2],
        ]);
    }

    public function removeKeepsBranchCoverageEntries(): void
    {
        $entry = ['lines' => [10 => 1], 'functions' => []];

        $result = PseudoFile::remove([__FILE__ => $entry, 'xdebug://debug-eval' => $entry]);

        Assert::same($result, [__FILE__ => $entry]);
    }

    public function removeOfEmptyDataIsEmpty(): void
    {
        Assert::same(PseudoFile::remove([]), []);
    }

    public function missingPathIsCheckedOncePerRun(): void
    {
        $path = $this->root . '/appears-later.php';
        Assert::true(PseudoFile::is($path));

        \file_put_contents($path, '<?php');

        Assert::true(PseudoFile::is($path));
    }

    public function realFileIsCheckedOncePerRun(): void
    {
        $path = $this->root . '/removed-later.php';
        \file_put_contents($path, '<?php');
        Assert::false(PseudoFile::is($path));

        \unlink($path);

        Assert::false(PseudoFile::is($path));
    }
}
