<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal\Middleware;

use Testo\Bridge\Rector\Testing\Internal\RectorFixtureProbe;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Discovers Rector rules carrying {@see TestRectorFixtures} and turns each into a test case —
 * "inline tests for rules". One case per rule (named after the rule class); a single test per
 * case (the {@see RectorFixtureProbe::fixture()} method), which {@see RectorFixtureInterceptor}
 * fans into one data set per fixture.
 *
 * Modelled on Testo's own `InlineFinder`.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Rector
 */
#[InterceptorOptions(order: -20_000, testType: RectorFixtureInterceptor::TYPE)]
final readonly class RectorFixtureFinder implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
        # Claim only the rule sources (`*.php`), never the co-located `*.php.inc` fixtures: those
        # declare classes too, but are not loadable PHP and must not be included/reflected.
        if ($file->path->extension() !== 'php') {
            return $next($file);
        }

        return $file->getClasses() !== [] ? true : $next($file);
    }

    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        $probe = new \ReflectionMethod(RectorFixtureProbe::class, 'fixture');

        foreach ($file->classes as $class) {
            if ($class->isAbstract() || $class->getAttributes(TestRectorFixtures::class) === []) {
                continue;
            }

            $case = $file->cases->define($class, $file, RectorFixtureInterceptor::TYPE);
            $case->tests->define($probe);
        }

        return $next($file);
    }
}
