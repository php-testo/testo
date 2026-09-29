<?php

declare(strict_types=1);

namespace Testo\Codecov\Exception;

/**
 * Thrown when a coverage report cannot be written to its target path.
 *
 * @api
 */
final class CoverageReportNotWritten extends \RuntimeException
{
    /**
     * @param string $path The directory or file that could not be written.
     * @param string $reason What the filesystem reported; empty when it said nothing.
     */
    public static function directory(string $path, string $reason): self
    {
        return new self(self::format('Unable to create the coverage report directory', $path, $reason));
    }

    /**
     * @param string $path The directory or file that could not be written.
     * @param string $reason What the filesystem reported; empty when it said nothing.
     */
    public static function file(string $path, string $reason): self
    {
        return new self(self::format('Unable to write the coverage report file', $path, $reason));
    }

    private static function format(string $action, string $path, string $reason): string
    {
        return $reason === ''
            ? \sprintf('%s `%s`.', $action, $path)
            : \sprintf('%s `%s`: %s.', $action, $path, $reason);
    }
}
