<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

use Testo\Assert\State\CompositeRecord;
use Testo\Assert\State\Record;

/**
 * Renders an assertion {@see Record} history tree into a plain, channel-friendly string.
 *
 * The output is intentionally ANSI-free: it travels through the messenger `assert-history`
 * channel and must render identically in the terminal and in TeamCity (whose service-message
 * escaping would mangle ANSI sequences). The channel name and timestamp header are added by the
 * renderer side, so the body carries only the assertion lines.
 *
 * Mirrors the previous output that lived in the core terminal/TeamCity renderers: one line per
 * record (`✓`/`✗`, the assertion text, an optional ` → context`), recursing into the failed
 * children of composite records.
 *
 * @internal
 */
final class HistoryRenderer
{
    /**
     * @param list<Record> $history
     */
    public static function render(array $history): string
    {
        $lines = [
            'Assertion history:',
        ];

        if ($history === []) {
            $lines[] = '  No assertions were made.';
            return \implode("\n", $lines);
        }

        foreach ($history as $record) {
            self::appendRecord($lines, $record, 0);
        }

        return \implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param int<0, max> $level
     */
    private static function appendRecord(array &$lines, Record $record, int $level): void
    {
        $indent = '  ' . \str_repeat('  ', $level);

        $text = (string) $record;
        $context = $record->getContext();
        $context === '' or $text .= ' → ' . $context;

        $lines[] = "{$indent}" . ($record->isSuccess() ? '✓' : '✗') . " {$text}";

        if ($record instanceof CompositeRecord) {
            foreach ($record->getRecords() as $sub) {
                $sub->isSuccess() or self::appendRecord($lines, $sub, $level + 1);
            }
        }
    }
}
