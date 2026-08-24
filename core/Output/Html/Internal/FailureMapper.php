<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Output\Rendering\Diff\DiffOp;
use Testo\Output\Rendering\Diff\MyersDiffer;
use Testo\Output\Rendering\StackTrace;

/**
 * A throwable as the report shows it: what failed, where, what the line looked like, and — when the
 * failure knows both sides of a comparison — the diff between them.
 *
 * The `previous` chain is flattened into `causedBy` with locations only: the outermost trace already
 * covers the call path, and repeating it per link would multiply the document's size for nothing.
 *
 * Diffs come from {@see ComparisonFailure}, which lives in `testo/assert` — a soft dependency, since a
 * project can assert with anything. Without it, or with a failure of another kind, the report keeps the
 * message and the trace and shows no diff, rather than inventing one.
 *
 * @internal
 */
final class FailureMapper
{
    /**
     * Longest file the source line is read out of. A generated fixture can be megabytes; a report is
     * not the place to page one in.
     */
    private const MAX_SOURCE_FILE_SIZE = 2 * 1024 * 1024;

    /**
     * Source lines already read, keyed by file. A failing assertion in a loop points at one line from
     * many frames, and the file is worth reading once.
     *
     * @var array<string, list<string>|null>
     */
    private array $sources = [];

    /**
     * @return array<non-empty-string, mixed>
     */
    public function map(\Throwable $failure, ?\ReflectionFunctionAbstract $boundary = null): array
    {
        $file = $failure->getFile();
        $line = $failure->getLine();

        $data = [
            'class' => $failure::class,
            'message' => $failure->getMessage(),
            'file' => $file,
            'line' => $line,
        ];

        $source = $this->sourceLine($file, $line);
        $source === null or $data['sourceLine'] = $source;

        $diff = self::diff($failure);
        $diff === null or $data['diff'] = $diff;

        $data['trace'] = self::trace($failure, $boundary);

        $causedBy = self::causedBy($failure);
        $causedBy === [] or $data['causedBy'] = $causedBy;

        return $data;
    }

    /**
     * The two sides of a comparison and the line-by-line edit script between them.
     *
     * @return array{expected: string, actual: string, lines: list<array{op: string, text: string}>}|null
     */
    private static function diff(\Throwable $failure): ?array
    {
        if (!\class_exists(ComparisonFailure::class) || !$failure instanceof ComparisonFailure) {
            return null;
        }

        $expected = $failure->getExpectedAsString();
        $actual = $failure->getActualAsString();

        $lines = [];
        foreach ((new MyersDiffer())->diff($expected, $actual) as $diffLine) {
            $lines[] = [
                'op' => match ($diffLine->op) {
                    DiffOp::Add => 'add',
                    DiffOp::Remove => 'del',
                    DiffOp::Context => 'ctx',
                },
                'text' => $diffLine->line,
            ];
        }

        return ['expected' => $expected, 'actual' => $actual, 'lines' => $lines];
    }

    /**
     * Frames trimmed at the test function, the same way every other Testo reporter trims them: below the
     * test is the runner's own pipeline, which is noise to anyone reading the failure.
     *
     * @return list<array{file: string, line: int|null, call: string}>
     */
    private static function trace(\Throwable $failure, ?\ReflectionFunctionAbstract $boundary): array
    {
        $frames = [];
        foreach (StackTrace::cutStackTrace($failure->getTrace(), $boundary) as $frame) {
            /** @var array{file?: string, line?: int, class?: string, type?: string, function?: string} $frame */
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';

            $frames[] = [
                'file' => $frame['file'] ?? '[internal function]',
                'line' => $frame['line'] ?? null,
                'call' => $class === ''
                    ? "{$function}()"
                    : $class . ($frame['type'] ?? '::') . "{$function}()",
            ];
        }

        return $frames;
    }

    /**
     * @return list<array{class: class-string, message: string, file: string, line: int}>
     */
    private static function causedBy(\Throwable $failure): array
    {
        $chain = [];
        $current = $failure->getPrevious();
        while ($current !== null) {
            $chain[] = [
                'class' => $current::class,
                'message' => $current->getMessage(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
            ];
            $current = $current->getPrevious();
        }

        return $chain;
    }

    /**
     * @return list<string>|null
     */
    private static function readSource(string $file): ?array
    {
        if (!\is_file($file) || !\is_readable($file)) {
            return null;
        }

        $size = \filesize($file);
        if ($size === false || $size > self::MAX_SOURCE_FILE_SIZE) {
            return null;
        }

        $lines = \file($file, \FILE_IGNORE_NEW_LINES);

        return $lines === false ? null : $lines;
    }

    /**
     * The source line the failure points at, trimmed of indentation, or null when the file cannot be
     * read — a failure inside an eval, a file already deleted, a stream the report has no business
     * paging in.
     */
    private function sourceLine(string $file, int $line): ?string
    {
        if ($line < 1) {
            return null;
        }

        if (!\array_key_exists($file, $this->sources)) {
            $this->sources[$file] = self::readSource($file);
        }

        $source = $this->sources[$file];
        if ($source === null) {
            return null;
        }

        $text = \trim($source[$line - 1] ?? '');

        return $text === '' ? null : $text;
    }
}
