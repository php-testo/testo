<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal;

use Rector\Application\ApplicationFileProcessor;
use Rector\Configuration\ConfigurationFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\Contract\Rector\RectorInterface;
use Rector\DependencyInjection\LazyContainerFactory;
use Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider;
use Rector\PhpParser\NodeTraverser\RectorNodeTraverser;
use Rector\Testing\Fixture\FixtureSplitter;
use Testo\Assert;

/**
 * Runs Rector rules against a fixture file and asserts the result, on a dedicated,
 * freshly-booted Rector container.
 *
 * The Testo-flavoured counterpart of Rector's `AbstractRectorTestCase`: it reuses Rector's
 * framework-agnostic services (container, file processor, fixture splitter) but replaces the
 * PHPUnit shell with a plain service that asserts via {@see Assert}.
 *
 * One instance per rule (built by {@see Middleware\RectorFixtureInterceptor} for the duration of
 * the rule's test) = one Rector container, dropped when that test finishes — so the cross-test
 * "forget the registered rules" dance `AbstractRectorTestCase` performs is unnecessary: we
 * never share a container between rules. Each fixture's temp file is removed immediately.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Rector
 */
final class RectorRunner
{
    private readonly ApplicationFileProcessor $fileProcessor;
    private readonly DynamicSourceLocatorProvider $sourceLocator;
    private readonly ConfigurationFactory $configurationFactory;

    /**
     * @param list<class-string<RectorInterface>> $rules
     */
    public function __construct(array $rules)
    {
        $rectorConfig = (new LazyContainerFactory())->create();
        $rectorConfig->boot();

        foreach ($rules as $rule) {
            $rectorConfig->rule($rule);
        }

        # Mirror AbstractRectorTestCase: hand the freshly-registered rules to the traverser.
        # `tagged()` returns a Traversable (Illuminate RewindableGenerator); avoid naming the
        # php-scoper-prefixed class and just iterate it.
        $tagged = $rectorConfig->tagged(RectorInterface::class);
        $rectors = \is_iterable($tagged) ? \iterator_to_array($tagged, false) : [];
        $rectorConfig->make(RectorNodeTraverser::class)->refreshPhpRectors($rectors);

        $this->fileProcessor = $rectorConfig->make(ApplicationFileProcessor::class);
        $this->sourceLocator = $rectorConfig->make(DynamicSourceLocatorProvider::class);
        $this->configurationFactory = $rectorConfig->make(ConfigurationFactory::class);
    }

    /**
     * Asserts that running the configured rules on a `*.php.inc` fixture produces its expected
     * output. A fixture with no `-----` separator is asserted to stay unchanged.
     *
     * @param non-empty-string $fixturePath
     */
    public function assertConverts(string $fixturePath): void
    {
        $contents = (string) \file_get_contents($fixturePath);
        [$input, $expected] = FixtureSplitter::containsSplit($contents)
            ? FixtureSplitter::splitFixtureFileContents($contents)
            : [$contents, $contents];

        $inputFile = $this->writeTempFile($input);

        try {
            SimpleParameterProvider::setParameter(Option::SOURCE, [$inputFile]);

            # This rule's Rector container is shared across all of its fixtures, but the source
            # locator caches the aggregate built from the FIRST file it sees (Rector only rebuilds
            # per file under PHPUnit). Reset it per fixture so reflection — and the node scope
            # derived from it — sees THIS fixture's classes; otherwise reflection-based rules would
            # have to compensate for the shared container themselves.
            $this->sourceLocator->reset();
            $this->sourceLocator->setFilePath($inputFile);
            $configuration = $this->configurationFactory->createForTests([$inputFile]);
            $this->fileProcessor->processFiles([$inputFile], $configuration);

            $changed = (string) \file_get_contents($inputFile);
        } finally {
            @\unlink($inputFile);
        }

        Assert::same($changed, $expected, \sprintf('Fixture "%s" was not converted as expected', \basename($fixturePath)));
    }

    /**
     * @return non-empty-string
     */
    private function writeTempFile(string $contents): string
    {
        $base = \tempnam(\sys_get_temp_dir(), 'testo-rector-');
        $base === false and throw new \RuntimeException('Unable to create a temporary fixture file');

        # Rector keys behaviour off the `.php` suffix; rename the tempnam handle accordingly.
        $path = $base . '.php';
        \rename($base, $path);
        \file_put_contents($path, $contents);

        return $path;
    }
}
