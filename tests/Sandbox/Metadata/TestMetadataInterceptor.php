<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\TestType;
use Testo\Output\Teamcity\Teamcity\Formatter;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Emits a `##teamcity[testMetadata …]` service message for every {@see TestMetadata} on the test.
 *
 * The message must land between the test's `testStarted` and `testFinished`, so it is written after
 * `$next()` — the test body has run and produced its output by then, which is what triggers
 * `testStarted` — but before the pipeline finishes and the reporter closes the node with
 * `testFinished`. It goes straight to the stream {@see \fwrite}, bypassing output capture, exactly as
 * the built-in TeamCity logger does for benchmark metrics.
 *
 * @see Formatter::testMetadata()
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::Test)]
final readonly class TestMetadataInterceptor implements TestRunInterceptor
{
    /**
     * @param resource $output Stream the metadata messages are written to.
     */
    public function __construct(private mixed $output = \STDOUT) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        try {
            return $next($info);
        } finally {
            $this->publish($info);
        }
    }

    private function publish(TestInfo $info): void
    {
        $reflection = $info->testDefinition->reflection;
        $baseDir = \dirname($reflection->getFileName() ?: '.');

        foreach ($reflection->getAttributes(TestMetadata::class) as $attribute) {
            $meta = $attribute->newInstance();
            $value = $this->resolveValue($meta, $baseDir);

            $value === '' or \fwrite($this->output, Formatter::testMetadata(
                testName: $info->name,
                name: $meta->name,
                value: $value,
                type: $meta->type->value,
                identity: $info->identity,
            ) . "\n");
        }
    }

    /**
     * Resolves a local file reference against the test's directory; a URL or an absolute path passes
     * through untouched, as do the non-file types.
     */
    private function resolveValue(TestMetadata $meta, string $baseDir): string
    {
        $isFileType = $meta->type === MetadataType::Image || $meta->type === MetadataType::Artifact;

        return match (true) {
            !$isFileType,
            $this->isUrl($meta->value),
            $this->isAbsolute($meta->value) => $meta->value,
            default => $baseDir . \DIRECTORY_SEPARATOR . \strtr($meta->value, '/', \DIRECTORY_SEPARATOR),
        };
    }

    private function isUrl(string $value): bool
    {
        return \preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1;
    }

    private function isAbsolute(string $value): bool
    {
        return $value !== '' && ($value[0] === '/' || \preg_match('#^[a-z]:[\\\\/]#i', $value) === 1);
    }
}
