<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Driver;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\Driver\NormalizePath;
use Testo\Test;

#[Test]
#[Covers(NormalizePath::class)]
final class NormalizePathTest
{
    use NormalizePath {
        normalizePath as public;
    }

    public function directoryPathEndsWithSeparator(): void
    {
        // Arrange
        $path = Path::create(__DIR__);

        // Act
        $result = self::normalizePath($path);

        // Assert
        Assert::true(\str_ends_with($result, \DIRECTORY_SEPARATOR));
    }

    public function filePathDoesNotEndWithSeparator(): void
    {
        // Arrange
        $path = Path::create(__FILE__);

        // Act
        $result = self::normalizePath($path);

        // Assert
        Assert::string($result)->notContains(\DIRECTORY_SEPARATOR . \DIRECTORY_SEPARATOR);
        Assert::false(\str_ends_with($result, \DIRECTORY_SEPARATOR));
    }

    public function resultContainsNativeSeparators(): void
    {
        // Arrange
        $path = Path::create(__DIR__);

        // Act
        $result = self::normalizePath($path);

        // Assert — no forward slashes on Windows
        if (\DIRECTORY_SEPARATOR === '\\') {
            Assert::false(\str_contains(\rtrim($result, '\\'), '/'));
        }
    }

    public function resultIsAbsolutePath(): void
    {
        // Arrange — relative path "src"
        $path = Path::create('src');

        // Act
        $result = self::normalizePath($path);

        // Assert
        Assert::string($result)->contains(\DIRECTORY_SEPARATOR . 'src');
    }

    public function directoryPathAppendsOnlyOneSeparator(): void
    {
        // Arrange
        $path = Path::create(__DIR__);

        // Act
        $result = self::normalizePath($path);

        // Assert — no double separator at the end
        $withoutTrailing = \rtrim($result, \DIRECTORY_SEPARATOR);
        Assert::same(\strlen($result) - \strlen($withoutTrailing), 1);
    }
}
