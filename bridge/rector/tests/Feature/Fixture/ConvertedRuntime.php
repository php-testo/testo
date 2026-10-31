<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Feature\Fixture;

/**
 * Executes converted PHP through the real CLI. A child process keeps converted classes out of
 * Rector's reflection process when the same fixture is converted again in another test.
 */
final class ConvertedRuntime
{
    /**
     * @return array<string, array{string, int, 2?: class-string<\Throwable>}>
     */
    public static function run(string $source): array
    {
        $root = \dirname(__DIR__, 5);
        $directory = \sys_get_temp_dir() . '/testo-converted-' . \bin2hex(\random_bytes(8));
        \mkdir($directory);
        $input = $directory . '/converted.php';
        $config = $directory . '/testo.php';
        $junit = $directory . '/junit.xml';
        $stderr = $directory . '/stderr.txt';
        \file_put_contents($input, $source);
        \file_put_contents($config, \sprintf(<<<'PHP'
            <?php
            require %s;
            return new \Testo\Application\Config\ApplicationConfig(
                suites: [new \Testo\Application\Config\SuiteConfig(
                    'Converted',
                    location: new \Testo\Application\Config\FinderConfig(include: [%s]),
                )],
            );
            PHP, \var_export($root . '/tests/bootstrap.php', true), \var_export($input, true)));

        try {
            $process = \proc_open(
                [\PHP_BINARY, $root . '/vendor/bin/testo', '--config=' . $config, '--json', '--log-junit=' . $junit],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                $pipes,
                $root,
            );
            if (!\is_resource($process)) {
                throw new \RuntimeException('Unable to start the converted test suite');
            }
            \fclose($pipes[0]);
            $stdout = (string) \stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
            $exit = \proc_close($process);
            $report = \json_decode($stdout, true);
            if (!\in_array($exit, [0, 1], true) || !\is_array($report) || !isset($report['status']) || !\is_file($junit)) {
                throw new \RuntimeException("Converted suite exited $exit without a report: $stdout\n" . \file_get_contents($stderr));
            }
            $failures = \array_column($report['failures'], null, 'test');
            $results = [];
            foreach ((new \SimpleXMLElement((string) \file_get_contents($junit)))->xpath('//testcase') as $case) {
                $name = (string) $case['classname'] . '::' . (string) $case['name'];
                $failure = $failures[$name] ?? null;
                $results[$name] = [$failure['status'] ?? 'passed', (int) $case['assertions']];
                if (($failure['status'] ?? null) === 'error') {
                    $results[$name][] = $failure['exception'];
                }
            }
            return $results;
        } finally {
            foreach ([$input, $config, $junit, $stderr] as $file) {
                \is_file($file) and \unlink($file);
            }
            \rmdir($directory);
        }
    }
}
