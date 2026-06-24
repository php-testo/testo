<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

use Testo\Core\Log\Message;
use Testo\Output\Terminal\Renderer\Style;

/**
 * Renders a stream of channel {@see Message}s for human-facing output (terminal, TeamCity, ...).
 *
 * Keeps track of the active channel and prints a colored channel header — the channel name in a
 * stable, name-derived color, followed by the (dimmed) time of the channel's first message — only
 * when the channel changes. Consecutive messages from the same channel are appended verbatim, with
 * no header and no inserted line breaks. The header is kept on its own line.
 *
 * Stateful: one instance per output stream; call {@see reset()} at each test boundary so every test
 * starts its channel grouping afresh. Callers must skip empty content (the returned string is only
 * guaranteed non-empty when the given content is).
 *
 * @internal
 */
final class ChannelRenderer
{
    /**
     * Channel of the last rendered message; `null` before the first message / after a reset.
     *
     * @var non-empty-string|null
     */
    private ?string $lastChannel = null;

    /**
     * Whether the last rendered content ended on a newline, so a header can be kept on its own line
     * without inserting blank lines.
     */
    private bool $lastEndedWithNewline = true;

    public function reset(): void
    {
        $this->lastChannel = null;
        $this->lastEndedWithNewline = true;
    }

    /**
     * @return non-empty-string
     */
    public function render(Message $message): string
    {
        $channel = $message->channel;
        $content = $message->content;

        if ($channel === $this->lastChannel) {
            $this->lastEndedWithNewline = \str_ends_with($content, "\n");
            return $content;
        }

        // Keep the header on its own line: break only if the previous content didn't already.
        $separator = $this->lastChannel !== null && !$this->lastEndedWithNewline ? "\n" : '';
        $this->lastChannel = $channel;
        $this->lastEndedWithNewline = \str_ends_with($content, "\n");

        return $separator . self::header($channel, $message->time) . "\n" . $content;
    }

    /**
     * A channel header: the channel name highlighted in a stable, name-derived color, followed by
     * the (dimmed) wall-clock time of the channel's first message. {@see Style} strips the ANSI when
     * colors are disabled, leaving a plain `[channel] HH:MM:SS.mmm` header.
     *
     * @param non-empty-string $channel
     * @return non-empty-string
     */
    private static function header(string $channel, float $time): string
    {
        // Black is omitted on purpose — it is invisible on a dark terminal.
        $palette = [
            Color::Cyan,
            Color::Magenta,
            Color::Yellow,
            Color::Green,
            Color::Blue,
            Color::Red,
            Color::White,
            Color::Gray,
        ];
        $color = $palette[\abs(\crc32($channel)) % \count($palette)];

        return Style::colorize("[{$channel}]", $color) . ' ' . Style::dim(self::formatTime($time));
    }

    /**
     * Formats a {@see \microtime()} timestamp as `HH:MM:SS.mmm` wall-clock time.
     *
     * @return non-empty-string
     */
    private static function formatTime(float $time): string
    {
        $seconds = (int) $time;
        $millis = \min(999, (int) \round(($time - (float) $seconds) * 1000.0));

        /** @var non-empty-string */
        return \date('H:i:s', $seconds) . \sprintf('.%03d', $millis);
    }
}
