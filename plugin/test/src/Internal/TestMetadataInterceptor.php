<?php

declare(strict_types=1);

namespace Testo\Test\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Test\MetadataType;
use Testo\Test\TestMetadata;

/**
 * Emits a `##teamcity[testMetadata …]` service message for every {@see TestMetadata} on the test.
 *
 * The message must land between the test's `testStarted` and `testFinished`, so it is written after
 * `$next()` — the test body has run and produced its output by then, which is what triggers
 * `testStarted` — but before the pipeline finishes and the reporter closes the node with `testFinished`.
 * It goes straight to the stream {@see \fwrite}, bypassing output capture, exactly as the built-in
 * TeamCity logger does for benchmark metrics. The message is built here rather than through the core
 * formatter so the plugin depends on nothing internal; the format is TeamCity's own stable protocol.
 *
 * @link https://www.jetbrains.com/help/teamcity/reporting-test-metadata.html
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
        $attributes = $reflection->getAttributes(TestMetadata::class);

        if ($attributes === []) {
            return;
        }

        $baseDir = \dirname($reflection->getFileName() ?: '.');
        $flowId = (string) $info->identity->pipelineId;

        foreach ($attributes as $attribute) {
            $meta = $attribute->newInstance();
            $value = $this->resolveValue($meta, $baseDir);

            $value === '' or \fwrite($this->output, $this->format([
                'testName' => $info->name,
                'name' => $meta->name,
                'type' => $meta->type->value,
                'value' => $value,
                'flowId' => $flowId,
            ]) . "\n");
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

    /**
     * Builds a `##teamcity[testMetadata …]` service message from already-resolved attribute values.
     *
     * @param array<non-empty-string, string> $attributes
     * @return non-empty-string
     */
    private function format(array $attributes): string
    {
        $parts = '';
        foreach ($attributes as $key => $value) {
            $parts .= " {$key}='{$this->escape($value)}'";
        }

        return "##teamcity[testMetadata{$parts}]";
    }

    /**
     * Escapes a value per the TeamCity service-message rules.
     */
    private function escape(string $value): string
    {
        return \str_replace(
            ['|', "'", "\n", "\r", '[', ']'],
            ['||', "|'", '|n', '|r', '|[', '|]'],
            $value,
        );
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
