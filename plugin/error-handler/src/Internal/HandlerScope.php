<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\ErrorHandler\CapturedError;

/**
 * One test's slice of the process-global error-handler stack: the capturing handler plus whatever
 * the test installs above it.
 *
 * The slice leaves the stack together with the test on every fiber suspension and comes back with
 * it on resumption, so a handler the test installed is in place when the test continues and absent
 * while a sibling runs. Errors are forwarded to the handler that was on top when the test started.
 *
 * @internal
 * @psalm-internal Testo\ErrorHandler
 */
final class HandlerScope
{
    /** @var list<CapturedError> */
    public array $errors = [];

    private readonly \Closure $handler;

    /** @var list<callable> Stack as it was before {@see install()}, bottom first. */
    private array $before = [];

    /** @var list<callable> Handlers the test installed above ours, bottom first. */
    private array $above = [];

    private ?\Closure $previous = null;
    private bool $removed = false;
    private bool $left = false;

    public function __construct()
    {
        $this->handler = function (int $severity, string $message, string $file, int $line): bool {
            (\error_reporting() & $severity) === 0 or $this->errors[] = new CapturedError($severity, $message, $file, $line);

            return $this->previous === null || (bool) ($this->previous)($severity, $message, $file, $line);
        };
    }

    public function install(): void
    {
        $this->before = self::snapshot();
        $previous = \set_error_handler($this->handler);
        $this->previous = $previous === null ? null : $previous(...);
    }

    /**
     * Takes our handler and everything above it off the stack for the time the test is suspended.
     */
    public function suspend(): void
    {
        $stack = self::snapshot();
        $position = self::position($stack, $this->handler);
        if ($position === null) {
            $this->removed = true;
            $this->above = [];
            return;
        }

        $this->above = \array_slice($stack, $position + 1);
        self::pop(\count($stack) - $position);
    }

    public function resume(): void
    {
        if ($this->removed) {
            return;
        }

        \set_error_handler($this->handler);
        self::push($this->above);
    }

    /**
     * Removes the test's slice and puts the stack back as it was before {@see install()}.
     */
    public function release(): void
    {
        $stack = self::snapshot();
        $position = self::position($stack, $this->handler);

        if ($position === null) {
            $this->removed = true;
            self::pop(\count($stack));
            self::push($this->before);
            return;
        }

        $this->left = \count($stack) - $position > 1;
        self::pop(\count($stack) - $position);
    }

    /**
     * Whether the test left the stack different from how it found it: a handler of its own still
     * installed, or ours gone.
     */
    public function changed(): bool
    {
        return $this->removed || $this->left;
    }

    public function removed(): bool
    {
        return $this->removed;
    }

    /**
     * The whole stack, bottom first, put back untouched.
     *
     * @return list<callable>
     */
    private static function snapshot(): array
    {
        $stack = [];
        while (true) {
            $top = \set_error_handler(static fn(): bool => false);
            \restore_error_handler();
            if ($top === null) {
                break;
            }

            $stack[] = $top;
            \restore_error_handler();
        }

        $stack = \array_reverse($stack);
        self::push($stack);

        return $stack;
    }

    /**
     * @param list<callable> $stack
     */
    private static function position(array $stack, \Closure $handler): ?int
    {
        foreach ($stack as $i => $entry) {
            if ($entry === $handler) {
                return $i;
            }
        }

        return null;
    }

    private static function pop(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            \restore_error_handler();
        }
    }

    /**
     * @param list<callable> $handlers Bottom first.
     */
    private static function push(array $handlers): void
    {
        foreach ($handlers as $handler) {
            \set_error_handler($handler);
        }
    }
}
