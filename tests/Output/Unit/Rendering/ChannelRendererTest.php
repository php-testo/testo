<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Output\Rendering\ChannelRenderer;
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

    public function differentOwnersOnOneChannelEachGetTheirOwnHeader(): void
    {
        $renderer = new ChannelRenderer();

        $out = self::stripAnsi(
            $renderer->render(self::message('stdout', "a1\n"), 1)
            . $renderer->render(self::message('stdout', "b1\n"), 2)
            . $renderer->render(self::message('stdout', "a2\n"), 1),
        );

        // Same channel, two switches of owner: every block opens its own header instead of collapsing
        // into one — this is what keeps interleaved tests' output readable.
        Assert::same(\substr_count($out, '[stdout] '), 3);
        Assert::true(
            \preg_match('/^\[stdout] [\d:.]+\na1\n\[stdout] [\d:.]+\nb1\n\[stdout] [\d:.]+\na2\n$/', $out) === 1,
            "Owner switches did not each open a header: {$out}",
        );
    }

    public function sameOwnerAndChannelStillAppendsContentVerbatim(): void
    {
        $renderer = new ChannelRenderer();
        $renderer->render(self::message('stdout', "first\n"), 7);

        $out = $renderer->render(self::message('stdout', 'second'), 7);

        Assert::same($out, 'second');
    }

    public function ownerLabelIsAppendedToTheHeader(): void
    {
        $renderer = new ChannelRenderer();

        $out = self::stripAnsi($renderer->render(self::message('stdout', "hi\n"), 42, 'quickBursts'));

        // The label rides after the time, so a reader sees whose block this is before its first line.
        Assert::true(
            \preg_match('/^\[stdout] [\d:.]+ · quickBursts\nhi\n$/', $out) === 1,
            "Owner label missing from the header: {$out}",
        );
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

    public function headerColorIsNameDerivedFromTheFullEightColorPalette(): void
    {
        $renderer = new ChannelRenderer();

        // abs(crc32('stderr')) % 8 == 1 -> Color::Cyan ("\033[36m"). Dropping Color::Cyan from the
        // palette (size 8 -> 7) shifts the mapping and would colorize this header green instead.
        $out = $renderer->render(self::message('stderr', "oops\n"));

        Assert::true(
            \str_starts_with($out, "\033[36m[stderr]\033[0m "),
            "Header not Cyan-colorized for 'stderr': " . \addcslashes($out, "\033"),
        );
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
