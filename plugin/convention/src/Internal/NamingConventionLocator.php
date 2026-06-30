<?php

declare(strict_types=1);

namespace Testo\Convention\Internal;

use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Locates test files, cases, and tests by configurable naming conventions.
 *
 * File matching: accepts files whose stem ends with {@see $caseSuffix} (e.g. `*Test.php`).
 * Case matching: non-abstract classes ending with the same suffix.
 * Test matching: methods (public by default) and standalone functions
 * starting with {@see $testPrefix} followed by a non-lowercase character (e.g. `testCreatesUser`).
 *
 * @see NamingConventionPlugin
 *
 * @internal
 * @psalm-internal Testo\Convention
 */
#[InterceptorOptions(
    testType: TestType::Test,
)]
final readonly class NamingConventionLocator implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    public function __construct(
        private string $caseSuffix = 'Test',
        private string $testPrefix = 'test',
        private bool $allowPrivate = false,
    ) {}

    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
        if ($file->path->extension() !== 'php') {
            return $next($file);
        }

        return \str_ends_with($file->path->stem(), $this->caseSuffix) ? true : $next($file);
    }

    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        foreach ($file->classes as $class) {
            if (!$class->isAbstract() && \str_ends_with($class->getName(), $this->caseSuffix)) {
                $case = $file->cases->define($class, $file, type: TestType::Test);
                foreach ($class->getMethods() as $method) {
                    if (!$this->allowPrivate && !$method->isPublic()) {
                        continue;
                    }

                    if ($this->testPrefix === '' || \preg_match("/^{$this->testPrefix}[^a-z]/", $method->getName()) === 1) {
                        $case->tests->define($method);
                    }
                }
            }
        }

        if ($file->functions === []) {
            return $next($file);
        }

        # Define a case for functions
        # Implement a lazy case definition
        $case = null;
        foreach ($file->functions as $function) {
            if ($this->testPrefix === '' || \preg_match("/^{$this->testPrefix}[^a-z]/", $function->getShortName()) === 1) {
                $case ??= $file->cases->define(null, $file, type: TestType::Test);
                $case->tests->define($function);
            }
        }

        return $next($file);
    }
}
