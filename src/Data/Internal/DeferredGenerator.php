<?php

declare(strict_types=1);

namespace Testo\Data\Internal;

/**
 * A wrapper around a generator that doesn't start the wrapped generator ASAP.
 *
 * @implements \Iterator<mixed, mixed>
 */
final class DeferredGenerator implements \Iterator
{
    private bool $started = false;
    private bool $finished = false;
    private \Generator $generator;
    private \Closure $handler;

    private function __construct() {}

    /**
     * @param \Closure(): mixed $handler
     */
    public static function fromHandler(\Closure $handler): self
    {
        $self = new self();
        $self->handler = $handler;
        return $self;
    }

    /**
     * Throw an exception into the generator.
     *
     * @note doesn't throw generator's exceptions; use {@see catch()} to handle them.
     */
    public function throw(\Throwable $exception): void
    {
        $this->started or throw new \LogicException('Cannot throw exception into a generator that was not started.');
        $this->finished and throw new \LogicException(
            'Cannot throw exception into a generator that was already finished.',
        );
        $this->generator->throw($exception);
    }

    /**
     * Send a value to the generator.
     *
     * @note doesn't throw generator's exceptions; use {@see catch()} to handle them.
     */
    public function send(mixed $value): mixed
    {
        $this->start();
        $this->finished and throw new \LogicException('Cannot send value to a generator that was already finished.');
        return $this->generator->send($value);
    }

    /**
     * Get the return value of the generator if it was finished.
     */
    public function getReturn(): mixed
    {
        $this->finished or throw new \LogicException('Cannot get return value of a generator that was not finished.');
        return $this->generator->getReturn();
    }

    /**
     * Get the current value of the generator.
     */
    public function current(): mixed
    {
        $this->start();
        return $this->generator->current();
    }

    /**
     * Get the current key of the generator.
     */
    public function key(): mixed
    {
        $this->start();
        return $this->generator->key();
    }

    /**
     * Start or resume the generator.
     */
    public function next(): void
    {
        if (!$this->started || $this->finished) {
            $this->finished or $this->start();
            return;
        }

        $this->generator->next();
    }

    /**
     * Check if the generator is not finished.
     *
     * @note It starts the Generator.
     */
    public function valid(): bool
    {
        $this->start();
        $result = $this->generator->valid() or $this->finished = true;
        return $result;
    }

    public function rewind(): void
    {
        $this->generator->rewind();
    }

    private static function getDummyGenerator(): \Generator
    {
        static $generator;

        if ($generator === null) {
            $generator = (static function (): \Generator {
                yield;
            })();
            $generator->current();
        }

        return $generator;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        try {
            $result = ($this->handler)();

            if ($result instanceof \Generator) {
                $this->generator = $result;
                return;
            }

            /** @psalm-suppress all */
            $this->generator = (static function (mixed $result): \Generator {
                return $result;
                yield;
            })($result);
            $this->finished = true;
        } catch (\Throwable $e) {
            $this->generator = self::getDummyGenerator();
            throw $e;
        } finally {
            unset($this->handler, $this->values);
        }
    }
}
