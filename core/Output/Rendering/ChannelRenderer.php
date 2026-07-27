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
 * when the channel changes. Consecutive messages of the same channel are appended verbatim, with
 * no header and no inserted line breaks. The header is kept on its own line.
 *
 * Stateful, and scoped to one block of output rather than to the stream: interleaved tests each get
 * their own instance, so a block always opens with a fresh header and never inherits another test's
 * channel. Call {@see reset()} at a boundary inside a block — data sets share their batch's — so the
 * next message opens a header of its own. Callers must skip empty content (the returned string is
 * only guaranteed non-empty when the given content is).
 *
 * @internal
 */
final class ChannelRenderer
{
    /**
     * Channel of the last rendered message, i.e. whose header is currently open; `null` before the
     * first message / after a reset.
     *
     * @var non-empty-string|null
     */
    private ?string $lastChannel = null;

    /**
     * Whether the last rendered content ended on a newline, so a header can be kept on its own line
     * without inserting blank lines.
     *
     * Only tracks content this renderer produced. Callers write other things to the same sink — a
     * test's result line, a batch header — which is why {@see reset()} restores the assumption: it is
     * called at a boundary the caller has just terminated with a newline of its own.
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
        $separator = $this->lastEndedWithNewline ? '' : "\n";
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
