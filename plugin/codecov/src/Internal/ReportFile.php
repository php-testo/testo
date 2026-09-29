<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Codecov\Exception\CoverageReportNotWritten;

/**
 * Filesystem writes shared by the coverage reports.
 *
 * @internal
 * @psalm-internal Testo\Codecov
 */
final class ReportFile
{
    /**
     * Writes the file, creating its parent directory when missing.
     *
     * @throws CoverageReportNotWritten
     */
    public static function write(string $path, string $content): void
    {
        self::directory(\dirname($path));

        \error_clear_last();
        @\file_put_contents($path, $content) === false and throw CoverageReportNotWritten::file(
            $path,
            self::lastError(),
        );
    }

    /**
     * Creates the directory with its parents when missing.
     *
     * @throws CoverageReportNotWritten
     */
    public static function directory(string $path): void
    {
        \error_clear_last();
        # The second `is_dir()` covers a directory created concurrently between the check and `mkdir()`.
        \is_dir($path) || @\mkdir($path, 0o755, true) || \is_dir($path) or throw CoverageReportNotWritten::directory(
            $path,
            self::lastError(),
        );
    }

    /**
     * The last PHP warning without its `function(): ` prefix, e.g. `Permission denied`.
     */
    private static function lastError(): string
    {
        $message = \error_get_last()['message'] ?? '';

        return \preg_replace('/^\w+\([^)]*\): /', '', $message) ?? $message;
    }
}
