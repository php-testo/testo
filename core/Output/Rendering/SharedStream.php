<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

/**
 * One output stream shared by several tests running at once.
 *
 * A line-oriented report cannot represent two open groups: a test's header, its data sets and its
 * result line only mean anything while they sit next to each other. So when tests interleave (fibers,
 * an event loop) exactly one of them writes live and the rest accumulate their block, which goes out
 * whole once the stream frees.
 *
 * The lease is taken by the first write and held until {@see close()} — not until the writer pauses,
 * because a group has to stay contiguous. Releasing it drains the blocks that finished meanwhile, in
 * the order they finished.
 *
 * Writes with no owner pass straight through: they belong to no test (a suite header, a case footer),
 * and those happen while no test is in flight anyway.
 *
 * @internal
 */
final class SharedStream
{
    /** @var resource */
    private $stream;

    /**
     * Test currently writing live; `null` when the stream is free.
     */
    private ?int $owner = null;

    /**
     * Block of every test that is not the one writing live, keyed by test id. A block is opened by
     * the first write and lives until {@see close()}; a write after that opens a new one from scratch.
     *
     * @var array<int, string>
     */
    private array $buffers = [];

    /**
     * Finished blocks waiting for the stream, in the order they finished. Ids are not kept — nothing
     * ever looks a block up, only their order matters, and each one already ends with the result line
     * that names its test.
     *
     * @var list<string>
     */
    private array $queue = [];

    /**
     * Whether the stream's tail is a newline, so a block never starts halfway through someone's line.
     */
    private bool $atLineStart = true;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * Write on behalf of `$owner`, or with no owner at all.
     *
     * @param int|null $owner The emitting test's run
     *        ({@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId}), so every data set of one
     *        test writes into the same block; `null` for output that belongs to no test, which is
     *        written through immediately.
     */
    public function write(?int $owner, string $text): void
    {
        if ($text === '') {
            return;
        }

        if ($owner === null || $this->owner === $owner) {
            $this->toStream($text);
            return;
        }

        if ($this->owner !== null) {
            $this->buffers[$owner] = ($this->buffers[$owner] ?? '') . $text;
            return;
        }

        # The stream is free, so this test takes it. Whatever it buffered while someone else held the
        # lease goes out as the head of the same block, so its output stays in one piece.
        $this->owner = $owner;
        $block = ($this->buffers[$owner] ?? '') . $text;
        unset($this->buffers[$owner]);
        $this->writeBlock($block);
    }

    /**
     * No more output for `$owner`: its block is complete.
     *
     * Goes out at once when the stream is free, and waits its turn otherwise. Closing the live writer
     * releases the lease, which lets the waiting blocks out.
     *
     * @param int $owner {@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId} of the test that finished.
     */
    public function close(int $owner): void
    {
        if ($this->owner === $owner) {
            # Its block is already on the stream — there is nothing to queue, only a lease to give up.
            $this->owner = null;
            $this->drain();
            return;
        }

        $block = $this->buffers[$owner] ?? '';
        unset($this->buffers[$owner]);
        $block === '' or $this->queue[] = $block;

        $this->owner === null and $this->drain();
    }

    /**
     * Put everything on the stream and start over — at a case or session boundary.
     *
     * Unlike {@see close()} this also drains blocks of tests that never finished: a test killed by a
     * timeout or an aborted run still produced output, and losing it is worse than printing a block
     * with no result line at its end.
     */
    public function flush(): void
    {
        $this->owner = null;
        $this->drain();

        foreach ($this->buffers as $block) {
            $this->writeBlock($block);
        }
        $this->buffers = [];
    }

    private function drain(): void
    {
        foreach ($this->queue as $block) {
            $this->writeBlock($block);
        }
        $this->queue = [];
    }

    /**
     * Opens a block on the stream, breaking the current line first so the block starts on its own.
     */
    private function writeBlock(string $block): void
    {
        $this->atLineStart or $this->toStream("\n");
        $this->toStream($block);
    }

    private function toStream(string $text): void
    {
        \fwrite($this->stream, $text);
        $this->atLineStart = \str_ends_with($text, "\n");
    }
}
