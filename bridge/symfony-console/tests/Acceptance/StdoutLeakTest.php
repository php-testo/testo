<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Acceptance;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Under `--json` the report must be the only thing on stdout — a machine consumer parses the whole
 * stream. The acceptance tests spawn a nested `run` in-process per case; a reporter of one that wrote
 * to the real STDOUT instead of the rebound {@see \Testo\Output\ConsoleStreams} would prepend its
 * terminal verse to that stream. Nothing in-process can catch this: the write goes straight to the
 * process fd, past the CommandTester buffer. So this drives the real binary as a subprocess and reads
 * its actual stdout.
 */
#[Test]
#[CoversNothing]
final class StdoutLeakTest
{
    public function jsonStdoutCarriesOnlyTheReport(): void
    {
        [$stdout, $stderr] = self::runBinary(
            '--json',
            '--suite=Bridge/SymfonyConsole/Acceptance',
            '--filter=RunCommandTest',
        );

        $report = \json_decode(\trim($stdout), true);

        Assert::true(
            $report !== null,
            "stdout must be a single JSON object; a nested run leaked terminal output before it:\n"
            . \substr($stdout, 0, 600)
            . "\n--- stderr ---\n" . \substr($stderr, 0, 600),
        );
        Assert::true(
            \is_array($report) && isset($report['status']),
            'stdout JSON must be the run report (carrying a "status")',
        );
    }

    /**
     * Runs the real `testo` binary from the repository root and returns [stdout, stderr].
     *
     * @return array{string, string}
     */
    private static function runBinary(string ...$args): array
    {
        $root = \dirname(__DIR__, 4);
        $command = [\PHP_BINARY, $root . '/vendor/bin/testo', ...$args];

        $process = \proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );
        \assert(\is_resource($process));
        \fclose($pipes[0]);

        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        return [$stdout, $stderr];
    }
}
