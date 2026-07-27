<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

use Testo\Core\Log\Message;
use Testo\Output\Terminal\Renderer\Style;

/**
 * Renders a stream of channel {@see Message}s for human-facing output (terminal, TeamCity, ...).
 *
 * Keeps track of the active group and prints a colored channel header — the channel name in a
 * stable, name-derived color, followed by the (dimmed) time of the group's first message — only
 * when the group changes. Consecutive messages of the same group are appended verbatim, with
 * no header and no inserted line breaks. The header is kept on its own line.
 *
 * A group is the *owner* and the channel taken together, so output of two owners never shares one
 * header even on the same channel: when tests interleave (fibers, an event loop) their lines land on
 * one stream, and a header re-prints on every switch instead of collapsing them into one block.
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
     * Group (owner + channel) of the last rendered message, i.e. whose header is currently open;
     * `null` before the first message / after a reset.
     */
    private ?string $lastGroup = null;

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
        $this->lastGroup = null;
        $this->lastEndedWithNewline = true;
    }

    /**
     * @param int|string|null $owner Whoever emitted the message — a {@see \Testo\Core\Context\TestIdentity::$id}
     *        for test output. Only ever compared, never displayed; pass `null` to group by channel alone.
     * @param non-empty-string|null $ownerLabel Human-readable name of the owner, appended to the header.
     *        Pass it only when the stream really carries several owners at once, so a sequential run
     *        keeps the plain `[channel] time` header.
     * @return non-empty-string
     */
    public function render(Message $message, int|string|null $owner = null, ?string $ownerLabel = null): string
    {
        $channel = $message->channel;
        $content = $message->content;
        $group = $owner === null ? $channel : "{$owner}\x00{$channel}";

        if ($group === $this->lastGroup) {
            $this->lastEndedWithNewline = \str_ends_with($content, "\n");
            return $content;
        }

        // Keep the header on its own line: break only if the previous content didn't already.
        $separator = $this->lastEndedWithNewline ? '' : "\n";
        $this->lastGroup = $group;
        $this->lastEndedWithNewline = \str_ends_with($content, "\n");

        return $separator . self::header($channel, $message->time, $ownerLabel) . "\n" . $content;
    }

    /**
     * A channel header: the channel name highlighted in a stable, name-derived color, followed by
     * the (dimmed) wall-clock time of the group's first message and, when given, the owner it belongs
     * to. {@see Style} strips the ANSI when colors are disabled, leaving a plain
     * `[channel] HH:MM:SS.mmm` header — or `[channel] HH:MM:SS.mmm · owner` when labelled.
     *
     * @param non-empty-string $channel
     * @param non-empty-string|null $ownerLabel
     * @return non-empty-string
     */
    private static function header(string $channel, float $time, ?string $ownerLabel = null): string
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
        $suffix = $ownerLabel === null ? '' : " · {$ownerLabel}";

        return Style::colorize("[{$channel}]", $color) . ' ' . Style::dim(self::formatTime($time) . $suffix);
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
