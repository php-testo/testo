<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Testo\Application\Config\SuiteConfig;
use Testo\Common\ErrorReporter;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Pipeline;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\FileLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * @internal
 * @psalm-internal Testo\Application
 */
final readonly class SuiteFactory
{
    public function __construct(
        private InterceptorProvider $interceptorProvider,
        private ErrorReporter $errorReporter,
    ) {}

    public function create(SuiteConfig $config, Filter $filter): SuiteInfo
    {
        $files = $this->getFilesIterator($config, $filter);
        $definitions = $this->getCaseDefinitions($config, $files, $filter);

        $cases = [];
        foreach ($definitions as $definition) {
            # Skip empty test cases
            if ($definition->tests->getTests() === []) {
                continue;
            }

            $cases[] = $definition;
        }

        return new SuiteInfo(
            name: $config->name,
            testCases: CaseDefinitions::fromArray(...$cases),
        );
    }

    /**
     * Locate test files based on the suite configuration and {@see FileLocatorInterceptor} interceptors.
     *
     * @return iterable<TokenizedFile>
     */
    private function getFilesIterator(SuiteConfig $config, Filter $filter): iterable
    {
        $locator = FileLocator::fromFinderConfig($config->location, $filter);

        # Prepare interceptors pipeline
        $interceptors = $this->interceptorProvider->fromConfig(FileLocatorInterceptor::class);

        /**
         * @see FileLocatorInterceptor::locateFile()
         * @var callable(TokenizedFile): (null|bool) $pipeline
         */
        $pipeline = Pipeline::prepare(
            new PipeOptions(includeTypes: $filter->type, excludeTypes: $filter->notType),
            ...$interceptors,
        )
            ->with(static fn(TokenizedFile $_): ?bool => null, 'locateFile');

        foreach ($locator->getIterator() as $fileReflection) {
            $match = $pipeline($fileReflection);

            if ($match === true) {
                yield $fileReflection;
            }
        }
    }

    /**
     * Fetch test case definitions from the given files using {@see CaseLocatorInterceptor} interceptors.
     *
     * @param iterable<TokenizedFile> $files
     * @return list<CaseDefinition>
     */
    private function getCaseDefinitions(SuiteConfig $config, iterable $files, Filter $filter): array
    {
        $cases = [];
        # Prepare interceptors pipeline
        $interceptors = $this->interceptorProvider->fromConfig(CaseLocatorInterceptor::class);

        /**
         * @see CaseLocatorInterceptor::locateTestCases()
         * @var callable(FileDefinitions): CaseDefinitions $pipeline
         */
        $pipeline = Pipeline::prepare(
            new PipeOptions(includeTypes: $filter->type, excludeTypes: $filter->notType),
            ...$interceptors,
        )
            ->with(
                static fn(FileDefinitions $definitions): CaseDefinitions => $definitions->cases,
                'locateTestCases',
            );

        foreach ($files as $file) {
            $fileDef = new FileDefinitions(
                $file,
                classes: DefinitionLocator::getClasses($file, $this->errorReporter),
                functions: DefinitionLocator::getFunctions($file, $this->errorReporter),
            );
            $result = $pipeline($fileDef);

            $cases = \array_merge($cases, $result->getCases());
        }

        return $cases;
    }
}
