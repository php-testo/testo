<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Output\Rendering\SharedStream;
use Testo\Test;

#[Test]
#[Covers(SharedStream::class)]
final class SharedStreamTest
{
    public function theFirstWriterStreamsLiveAndTheRestWait(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(2, "b1\n");
            $stream->write(1, "a2\n");
        });

        // Test 1 took the lease with its first write, so only its lines reach the stream; test 2 is
        // held back rather than cutting through the middle of test 1's block.
        Assert::same($out, "a1\na2\n");
    }

    public function releasingTheLeaseLetsTheFinishedBlocksOutWhole(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(2, "b1\n");
            $stream->write(2, "b2\n");
            $stream->close(2);
            $stream->write(1, "a2\n");
            $stream->close(1);
        });

        // Test 2 finished while test 1 held the lease, so its whole block lands the moment test 1
        // releases — after test 1's output, never spliced into it.
        Assert::same($out, "a1\na2\nb1\nb2\n");
    }

    public function blocksAreDrainedInTheOrderTheyFinished(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a\n");
            $stream->write(2, "b\n");
            $stream->write(3, "c\n");
            $stream->close(3);
            $stream->close(2);
            $stream->close(1);
        });

        Assert::same($out, "a\nc\nb\n");
    }

    public function aWaitingWriterTakesTheLeaseAndKeepsItsBlockInOnePiece(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(2, "b1\n");
            $stream->close(1);
            $stream->write(2, "b2\n");
        });

        // Test 2 buffered "b1" while test 1 was live; on taking the lease it must flush that first and
        // then continue live, so the two halves of its block stay adjacent.
        Assert::same($out, "a1\nb1\nb2\n");
    }

    public function aBlockNeverStartsHalfwayThroughALine(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, 'a-no-newline');
            $stream->write(2, "b\n");
            $stream->close(2);
            $stream->close(1);
        });

        // Test 1 left the cursor mid-line, so test 2's block has to break it rather than glue itself
        // onto the tail of a foreign line.
        Assert::same($out, "a-no-newline\nb\n");
    }

    public function writingAfterCloseOpensAFreshBlock(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(2, "b1\n");
            $stream->write(3, "c1\n");
            $stream->close(2);
            $stream->write(2, "b-late\n");
            $stream->close(3);
            $stream->close(2);
            $stream->close(1);
        });

        // The late write opens a second block for test 2 that queues on its own terms — had it
        // reopened the closed one, "b-late" would have come out attached to "b1", ahead of "c1".
        Assert::same($out, "a1\nb1\nc1\nb-late\n");
    }

    public function outputOwnedByNoTestGoesStraightThrough(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(null, "framework\n");
            $stream->write(1, "a2\n");
        });

        Assert::same($out, "a1\nframework\na2\n");
    }

    public function flushDrainsBlocksOfTestsThatNeverFinished(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->write(2, "b-hung\n");
            $stream->flush();
        });

        // Test 2 never closed — a hang or an abort. Its output is printed rather than dropped.
        Assert::same($out, "a1\nb-hung\n");
    }

    public function closingATestThatWroteNothingEmitsNothing(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, "a1\n");
            $stream->close(2);
            $stream->close(1);
        });

        Assert::same($out, "a1\n");
    }

    public function emptyWritesAreIgnoredAndDoNotTakeTheLease(): void
    {
        $out = self::capture(static function (SharedStream $stream): void {
            $stream->write(1, '');
            $stream->write(2, "b1\n");
            $stream->write(1, "a1\n");
        });

        // Test 1's empty write must not claim the stream, or test 2 would be buffered behind a test
        // that has nothing to say.
        Assert::same($out, "b1\n");
    }

    /**
     * @param \Closure(SharedStream): void $scenario
     */
    private static function capture(\Closure $scenario): string
    {
        $handle = \fopen('php://memory', 'rb+');
        \assert($handle !== false);

        try {
            $scenario(new SharedStream($handle));
            \rewind($handle);
            $out = \stream_get_contents($handle);
        } finally {
            \fclose($handle);
        }

        return $out === false ? '' : $out;
    }
}
