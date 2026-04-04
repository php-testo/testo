<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\LineStatus;
use Testo\Test;

#[Test]
final class CoverageResultTest
{
    public function emptyByDefault(): void
    {
        $result = new CoverageResult();

        Assert::same($result->files, []);
    }

    public function fromRawDataLineOnly(): void
    {
        $raw = [
            '/src/Foo.php' => [5 => 1, 6 => -1, 7 => -2],
            '/src/Bar.php' => [10 => 1],
        ];

        // Act
        $result = CoverageResult::fromRawData($raw);

        // Assert
        Assert::count($result->files, 2);
        Assert::same($result->files['/src/Foo.php']->lines[5], LineStatus::Executed);
        Assert::same($result->files['/src/Foo.php']->lines[6], LineStatus::NotExecuted);
        Assert::same($result->files['/src/Foo.php']->lines[7], LineStatus::Dead);
        Assert::same($result->files['/src/Bar.php']->lines[10], LineStatus::Executed);
    }

    public function fromRawDataSkipsInvalidStatuses(): void
    {
        $raw = [
            '/src/Foo.php' => [5 => 1, 6 => 99],
        ];

        $result = CoverageResult::fromRawData($raw);

        Assert::count($result->files['/src/Foo.php']->lines, 1);
    }

    public function fromRawDataSkipsEmptyFiles(): void
    {
        $raw = [
            '/src/Empty.php' => [5 => 99, 6 => 42],
        ];

        $result = CoverageResult::fromRawData($raw);

        Assert::same($result->files, []);
    }

    public function fromRawDataBranchFormat(): void
    {
        $raw = [
            '/src/Foo.php' => [
                'lines' => [5 => 1, 6 => -1],
                'functions' => [
                    'Foo->bar' => [
                        'branches' => [
                            0 => [
                                'op_start' => 0,
                                'op_end' => 3,
                                'line_start' => 5,
                                'line_end' => 6,
                                'hit' => 1,
                                'out' => [4, 7],
                                'out_hit' => [1, 0],
                            ],
                        ],
                        'paths' => [
                            ['path' => [0, 4], 'hit' => 1],
                            ['path' => [0, 7], 'hit' => 0],
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = CoverageResult::fromRawData($raw);

        // Assert lines
        Assert::same($result->files['/src/Foo.php']->lines[5], LineStatus::Executed);
        Assert::same($result->files['/src/Foo.php']->lines[6], LineStatus::NotExecuted);

        // Assert functions
        $functions = $result->files['/src/Foo.php']->functions;
        Assert::count($functions, 1);

        $fn = $functions['Foo->bar'];
        Assert::same($fn->name, 'Foo->bar');
        Assert::count($fn->branches, 1);
        Assert::true($fn->branches[0]->hit);
        Assert::same($fn->branches[0]->out, [4, 7]);
        Assert::count($fn->paths, 2);
        Assert::true($fn->paths[0]->hit);
        Assert::false($fn->paths[1]->hit);
    }

    public function mergeCombinesFiles(): void
    {
        $a = CoverageResult::fromRawData([
            '/src/Foo.php' => [5 => 1],
        ]);
        $b = CoverageResult::fromRawData([
            '/src/Bar.php' => [10 => 1],
        ]);

        $merged = $a->merge($b);

        Assert::count($merged->files, 2);
    }

    public function mergeSameFileCombinesLines(): void
    {
        $a = CoverageResult::fromRawData([
            '/src/Foo.php' => [5 => -1, 6 => 1],
        ]);
        $b = CoverageResult::fromRawData([
            '/src/Foo.php' => [5 => 1, 7 => -1],
        ]);

        $merged = $a->merge($b);

        Assert::count($merged->files, 1);
        Assert::same($merged->files['/src/Foo.php']->lines[5], LineStatus::Executed);
        Assert::same($merged->files['/src/Foo.php']->lines[6], LineStatus::Executed);
        Assert::same($merged->files['/src/Foo.php']->lines[7], LineStatus::NotExecuted);
    }
}
