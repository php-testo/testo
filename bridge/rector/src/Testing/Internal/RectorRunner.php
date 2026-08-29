<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal;

use Psr\Log\LoggerInterface;
use Rector\Application\ApplicationFileProcessor;
use Rector\Configuration\ConfigurationFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\Contract\Rector\RectorInterface;
use Rector\DependencyInjection\LazyContainerFactory;
use Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider;
use Rector\PhpParser\NodeTraverser\RectorNodeTraverser;
use Internal\Path;
use Rector\Testing\Fixture\FixtureSplitter;
use Testo\Assert;
use Testo\Common\Messenger;

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
final readonly class RectorRunner
{
    private ApplicationFileProcessor $fileProcessor;
    private DynamicSourceLocatorProvider $sourceLocator;
    private ConfigurationFactory $configurationFactory;
    private LoggerInterface $channel;

    /**
     * @param list<class-string<RectorInterface>> $rules
     */
    public function __construct(Messenger $messenger, array $rules)
    {
        $this->channel = $messenger->channel('rector-fixture.php');
        $rectorConfig = (new LazyContainerFactory())->create();
        $rectorConfig->boot();

        foreach ($rules as $rule) {
            $rectorConfig->rule($rule);
        }

        # Mirror AbstractRectorTestCase: hand the freshly-registered rules to the traverser.
        # `findByContract()` returns them as a plain, 0-indexed list.
        $rectors = $rectorConfig->findByContract(RectorInterface::class);
        $rectorConfig->make(RectorNodeTraverser::class)->refreshPhpRectors($rectors);

        $this->fileProcessor = $rectorConfig->make(ApplicationFileProcessor::class);
        $this->sourceLocator = $rectorConfig->make(DynamicSourceLocatorProvider::class);
        $this->configurationFactory = $rectorConfig->make(ConfigurationFactory::class);
    }

    /**
     * Asserts that running the configured rules on a `*.php.inc` fixture produces its expected
     * output. A fixture with no `-----` separator is asserted to stay unchanged.
     */
    public function assertConverts(Path $fixturePath): void
    {
        $contents = (string) \file_get_contents((string) $fixturePath);
        [$input, $expected] = FixtureSplitter::containsSplit($contents)
            ? FixtureSplitter::splitFixtureFileContents($contents)
            : [$contents, $contents];
        $this->channel->debug("# Input:\n$input");
        $this->channel->debug("# Expected:\n$expected");

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

        Assert::same($changed, $expected, \sprintf('Fixture "%s" was not converted as expected', $fixturePath->name()));
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
