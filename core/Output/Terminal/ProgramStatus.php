<?php

declare(strict_types=1);

namespace Testo\Output\Terminal;

/**
 * Reports what the program is doing to the terminal it runs in, through the OSC 7501 program status
 * protocol (<https://www.superlogical.com/rex/docs/build/program-status>).
 *
 * The terminal shows the state in its tab or window chrome; terminals that do not know the protocol
 * ignore it. A disabled instance writes nothing, so callers report unconditionally.
 *
 * ```php
 * $status = ProgramStatus::detect(\STDOUT);
 * $status->working(progress: 40, message: 'Unit');
 * $status->done('120 passed');
 * ```
 *
 * @api
 */
final class ProgramStatus
{
    /**
     * Opt-out switch: `0`, `false`, `off` or `no` turns the reports off.
     */
    public const ENV_SWITCH = 'TESTO_TERMINAL_STATUS';

    private const APP = 'testo';

    /**
     * The protocol's limit on a decoded `msg`.
     */
    private const MESSAGE_LIMIT = 2048;

    private ?string $last = null;

    /**
     * @param resource|null $stream Where the sequences go; null disables the reports.
     */
    public function __construct(
        private readonly mixed $stream,
    ) {}

    /**
     * A reporter for `$stream` that writes only where a person watches a terminal: the stream is an
     * interactive TTY that understands escape sequences, and the user has not switched the reports off.
     *
     * @param resource $stream
     */
    public static function detect(mixed $stream): self
    {
        return new self(self::isSupported($stream) && !self::isSwitchedOff() ? $stream : null);
    }

    public static function disabled(): self
    {
        return new self(null);
    }

    public function isEnabled(): bool
    {
        return $this->stream !== null;
    }

    /**
     * @param int<0, 100>|null $progress Percent done; null for an indeterminate state.
     */
    public function working(?int $progress = null, string $message = ''): void
    {
        $this->report('working', $progress, $message);
    }

    /**
     * Waiting for the user to answer a question.
     */
    public function blocked(string $message = ''): void
    {
        $this->report('blocked', null, $message, kind: 'question');
    }

    public function done(string $message = ''): void
    {
        $this->report('done', null, $message);
    }

    public function error(string $message = ''): void
    {
        $this->report('error', null, $message);
    }

    /**
     * Removes the program's state from the terminal.
     */
    public function clear(): void
    {
        $this->report('clear', null, '');
    }

    /**
     * @param resource $stream
     */
    private static function isSupported(mixed $stream): bool
    {
        if (!\is_resource($stream) || !\stream_isatty($stream)) {
            return false;
        }

        # A Windows console prints escape sequences as text unless VT processing is on for it.
        return \DIRECTORY_SEPARATOR !== '\\'
            || (\function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support($stream));
    }

    private static function isSwitchedOff(): bool
    {
        $value = $_SERVER[self::ENV_SWITCH] ?? $_ENV[self::ENV_SWITCH] ?? \getenv(self::ENV_SWITCH);

        return \is_string($value) && \in_array(\strtolower(\trim($value)), ['0', 'false', 'off', 'no'], true);
    }

    /**
     * The protocol discards a whole report whose message is not valid UTF-8, holds a control character
     * (C0, DEL or C1) or runs past the limit, so all three are fixed here rather than losing the report.
     */
    private static function singleLine(string $message): string
    {
        $message = \mb_scrub($message, 'UTF-8');
        $message = \trim((string) \preg_replace('/\p{Cc}+/u', ' ', $message));

        return \strlen($message) <= self::MESSAGE_LIMIT
            ? $message
            : \mb_strcut($message, 0, self::MESSAGE_LIMIT, 'UTF-8');
    }

    private function report(string $state, ?int $progress, string $message, ?string $kind = null): void
    {
        if ($this->stream === null) {
            return;
        }

        $pairs = ['state=' . $state, 'app=' . self::APP];
        $progress === null or $pairs[] = 'progress=' . \max(0, \min(100, $progress));
        $kind === null or $pairs[] = 'kind=' . $kind;
        $message = self::singleLine($message);
        $message === '' or $pairs[] = 'msg=' . \base64_encode($message);

        $sequence = "\033]7501;" . \implode(':', $pairs) . "\033\\";

        # Each report replaces the previous one whole, so a repeat changes nothing on the terminal side.
        if ($sequence === $this->last) {
            return;
        }

        $this->last = $sequence;
        @\fwrite($this->stream, $sequence);
    }
}
