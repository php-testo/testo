<?php

declare(strict_types=1);

namespace Testo\Common;

use Internal\Container\Attribute\ScopeShared;
use Testo\Core\Log\Level;

/**
 * Reports internal throwables that Testo would otherwise suppress into the {@see Messenger::CHANNEL_STDERR}
 * channel, so framework-level faults stay visible instead of vanishing silently.
 *
 * Writes through the {@see Messenger} hub: the message is recorded in the active scope and announced
 * via a {@see \Testo\Event\Message\MessageReceived} event, so the output renderers (terminal, TeamCity)
 * pick it up. Within a running test it also lands in that test's buffer; errors raised outside any test
 * are still surfaced live by the renderers.
 *
 * @internal
 */
#[ScopeShared]
final readonly class ErrorReporter
{
    public function __construct(
        private Messenger $messenger,
    ) {}

    /**
     * Render a throwable (with its `previous` chain) as a compact, human-readable string.
     *
     * @return non-empty-string
     */
    public static function format(\Throwable $e): string
    {
        $parts = [];
        $current = $e;

        do {
            $header = \sprintf('%s: %s', $current::class, $current->getMessage());
            $location = \sprintf('  at %s:%d', $current->getFile(), $current->getLine());
            $parts[] = $header . "\n" . $location . "\n" . self::formatTrace($current->getTrace());
        } while ($current = $current->getPrevious());

        return \implode("\n\nCaused by:\n", $parts);
    }

    /**
     * Record a throwable in the given channel ({@see Messenger::CHANNEL_STDERR} by default).
     *
     * @param non-empty-string $channel
     */
    public function report(
        \Throwable $e,
        Level $level = Level::Error,
        string $channel = Messenger::CHANNEL_STDERR,
    ): void {
        $this->messenger->log($channel, self::format($e), $level);
    }

    /**
     * @param list<array{args?: array<array-key, mixed>, class?: class-string, file?: string, function?: string, line?: int, type?: '->'|'::'}> $trace
     */
    private static function formatTrace(array $trace): string
    {
        $lines = [];

        foreach ($trace as $i => $frame) {
            $function = $frame['function'] ?? '{closure}';
            $location = isset($frame['file'], $frame['line'])
                ? \sprintf('%s(%d)', $frame['file'], $frame['line'])
                : '[internal function]';
            $call = isset($frame['class'], $frame['type'])
                ? $frame['class'] . $frame['type'] . $function . '()'
                : $function . '()';
            $lines[] = \sprintf('#%d %s: %s', $i, $location, $call);
        }

        return \implode("\n", $lines);
    }
}
