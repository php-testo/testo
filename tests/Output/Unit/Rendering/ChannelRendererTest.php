<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Output\Rendering\ChannelRenderer;
use Testo\Output\Rendering\Color;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Test;

#[Test]
#[Covers(ChannelRenderer::class)]
final class ChannelRendererTest
{
    public function firstMessageEmitsHeaderWithoutLeadingSeparator(): void
    {
        $renderer = new ChannelRenderer();

        $out = self::stripAnsi($renderer->render(self::message('stdout', "hello\n")));

        // No previous channel, so no leading "\n" separator; header on its own line then content.
        Assert::true(\str_starts_with($out, '[stdout] '));
        Assert::true(\str_ends_with($out, "\nhello\n"));
        Assert::string($out)->notContains("\n\n");
    }

    public function consecutiveSameChannelMessageReturnsContentVerbatim(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', "first\n"));

        // Same channel: content is returned verbatim, no header, no inserted breaks (kept raw, no ANSI to strip).
        $out = $renderer->render(self::message('stdout', 'second'));

        Assert::same($out, 'second');
    }

    public function channelChangeInsertsSeparatorWhenPreviousDidNotEndWithNewline(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', 'no-newline'));

        $out = self::stripAnsi($renderer->render(self::message('stderr', 'oops')));

        // Previous content lacked a trailing newline -> a "\n" separator keeps the header on its own line.
        Assert::true(\str_starts_with($out, "\n[stderr] "));
        Assert::true(\str_ends_with($out, "\noops"));
    }

    public function channelChangeOmitsSeparatorWhenPreviousEndedWithNewline(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', "done\n"));

        $out = self::stripAnsi($renderer->render(self::message('stderr', 'oops')));

        // Previous content already ended on a newline -> no extra separator before the header.
        Assert::true(\str_starts_with($out, '[stderr] '));
        Assert::string($out)->notContains("\n\n");
    }

    public function resetMakesSameChannelEmitHeaderAgain(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', "hello\n"));

        $renderer->reset();
        $out = self::stripAnsi($renderer->render(self::message('stdout', "again\n")));

        // After reset the channel is forgotten, so a fresh header is emitted with no leading separator.
        Assert::true(\str_starts_with($out, '[stdout] '));
        Assert::string($out)->notContains("\n\n");
    }

    public function resetAssumesTheCallerLeftTheCursorAtLineStart(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', 'no-newline'));

        $renderer->reset();
        $out = self::stripAnsi($renderer->render(self::message('stdout', "again\n")));

        // The renderer sees only its own content, and reset() marks a boundary the caller terminated
        // itself (a test's result line) — so it must not insert a separator of its own.
        Assert::true(\str_starts_with($out, '[stdout] '), "Unwanted separator after reset: {$out}");
    }

    public function headerFormatsTimeAsWallClockWithMilliseconds(): void
    {
        $renderer = new ChannelRenderer();

        // 1.5 seconds past the epoch -> millisecond fraction is .500.
        $out = self::stripAnsi($renderer->render(self::message('sql', 'q', 1.5)));

        Assert::true(
            \preg_match('/^\[sql] \d{2}:\d{2}:\d{2}\.500\n/', $out) === 1,
            "Header time not formatted as HH:MM:SS.500: {$out}",
        );
    }

    public function formatTimeClampsMillisecondsToNineNineNine(): void
    {
        $renderer = new ChannelRenderer();

        // A fraction that rounds to 1000ms must be clamped to 999 rather than rolling the seconds.
        $out = self::stripAnsi($renderer->render(self::message('sql', 'q', 9.9999)));

        Assert::true(
            \preg_match('/^\[sql] \d{2}:\d{2}:\d{2}\.999\n/', $out) === 1,
            "Header milliseconds not clamped to 999: {$out}",
        );
    }

    public function aChannelAlwaysGetsTheSameHeaderColor(): void
    {
        // Derived from the name, so it survives across renderers — that is what lets a reader track one
        // channel down a report. Which color any given name lands on is crc32 arithmetic and pinning it
        // would freeze the palette's order against a reordering that changes nothing.
        Assert::same(self::headerColor('stderr'), self::headerColor('stderr'));
        Assert::same(self::headerColor('sql'), self::headerColor('sql'));
    }

    public function everyColorOfThePaletteIsReachable(): void
    {
        $seen = [];
        for ($i = 0; $i < 200; ++$i) {
            $seen[self::headerColor("channel-{$i}")] = true;
        }

        // The size claim, stated as a set so it holds whatever order the palette sits in: drop a color
        // and this shrinks. Black stays out on purpose — invisible on a dark terminal.
        Assert::same(\count($seen), 8);
        Assert::false(isset($seen[Color::Black->value]));
    }

    /**
     * The color a channel's header is rendered in, as its raw escape sequence.
     *
     * Colorization hangs on a process-global flag in {@see Style}, and building a `TerminalPlugin` sets
     * it — so a case elsewhere in the run can leave it off and there would be no color here to read.
     * Turning it on for the render and putting back what was there keeps this independent of whatever
     * order the finder walks files in, which differs between platforms.
     *
     * @param non-empty-string $channel
     */
    private static function headerColor(string $channel): string
    {
        $colors = Style::areColorsEnabled();
        Style::setColorsEnabled(true);

        try {
            $out = (new ChannelRenderer())->render(self::message($channel, "x\n"));
        } finally {
            Style::setColorsEnabled($colors);
        }

        \preg_match('/^\033\[\d+m/', $out, $matches);
        Assert::notSame($matches, [], "Header not colorized for '{$channel}': " . \addcslashes($out, "\033"));

        return $matches[0];
    }

    private static function stripAnsi(string $text): string
    {
        return (string) \preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * @param non-empty-string $channel
     * @param non-empty-string $content
     */
    private static function message(string $channel, string $content, float $time = 0.0): Message
    {
        return new Message($time, $channel, Level::Info, $content);
    }
}
