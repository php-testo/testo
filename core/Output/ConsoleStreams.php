<?php

declare(strict_types=1);

namespace Testo\Output;

use Internal\Container\Attribute\ScopeShared;

/**
 * The process's stdout/stderr as an injectable pair of streams.
 *
 * Reporters write through this rather than the {@see \STDOUT} / {@see \STDERR} constants, so binding a
 * different pair points a run somewhere the constants cannot reach — a memory stream, a discard stream.
 *
 * @api
 */
#[ScopeShared]
final readonly class ConsoleStreams
{
    /** @var resource */
    public mixed $stdout;

    /** @var resource */
    public mixed $stderr;

    /**
     * @param resource|null $stdout Defaults to {@see \STDOUT}.
     * @param resource|null $stderr Defaults to {@see \STDERR}.
     */
    public function __construct($stdout = null, $stderr = null)
    {
        $this->stdout = $stdout ?? \STDOUT;
        $this->stderr = $stderr ?? \STDERR;
    }
}
