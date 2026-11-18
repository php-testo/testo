<?php

declare(strict_types=1);

namespace Testo\Test\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Metric\Memory;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Time;
use Testo\Core\Value\TestType;
use Testo\Metric\Metric;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Test\MetadataType;
use Testo\Test\TestMetadata;

/**
 * Adds every {@see TestMetadata} declared on the test to its result's metadata.
 *
 * Runs closest to the test, so each data set and each retry attempt carries its own copy and the result
 * a reporter finally renders holds the declared values exactly once.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, testType: TestType::Test)]
final readonly class TestMetadataInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $reflection = $info->testDefinition->reflection;
        $attributes = $reflection->getAttributes(TestMetadata::class);
        if ($attributes === []) {
            return $next($info);
        }

        // Built ahead of the body, so a malformed declaration fails before the test spends its run.
        $baseDir = \dirname($reflection->getFileName() ?: '.');
        $entries = \array_map(
            static fn(\ReflectionAttribute $attribute): MetadataEntry => self::entry($attribute->newInstance(), $baseDir),
            $attributes,
        );

        $result = $next($info);
        return $result->withMetadata($result->metadata->with(...$entries));
    }

    private static function entry(TestMetadata $meta, string $baseDir): MetadataEntry
    {
        return match ($meta->type) {
            MetadataType::Number => MetadataEntry::number($meta->name, self::number($meta)),
            MetadataType::Milliseconds => MetadataEntry::number($meta->name, new Metric(self::number($meta), Time::Milliseconds)),
            MetadataType::Bytes => MetadataEntry::number($meta->name, new Metric(self::number($meta), Memory::Bytes)),
            MetadataType::Percent => MetadataEntry::number($meta->name, new Metric(self::number($meta), Percent::Percent)),
            MetadataType::Text => MetadataEntry::text($meta->name, $meta->value),
            MetadataType::Link => MetadataEntry::link($meta->name, $meta->value),
            MetadataType::Image => MetadataEntry::image($meta->name, self::location($meta->value, $baseDir)),
            MetadataType::Artifact => MetadataEntry::artifact($meta->name, self::location($meta->value, $baseDir)),
        };
    }

    /**
     * @throws \InvalidArgumentException When a numeric type is given a value that is not a number.
     */
    private static function number(TestMetadata $meta): int|float
    {
        \is_numeric($meta->value) or throw new \InvalidArgumentException(
            "Test metadata `{$meta->name}` of type `{$meta->type->value}` expects a number, got `{$meta->value}`.",
        );

        return $meta->value + 0;
    }

    /**
     * A relative path resolves against the test's directory; a URL or an absolute path passes through.
     */
    private static function location(string $value, string $baseDir): string
    {
        $isUrl = \preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1;
        $isAbsolute = $value !== '' && ($value[0] === '/' || \preg_match('#^[a-z]:[\\\\/]#i', $value) === 1);

        return $isUrl || $isAbsolute
            ? $value
            : $baseDir . \DIRECTORY_SEPARATOR . \strtr($value, '/', \DIRECTORY_SEPARATOR);
    }
}
