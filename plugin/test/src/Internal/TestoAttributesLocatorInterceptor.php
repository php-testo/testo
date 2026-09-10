<?php

declare(strict_types=1);

namespace Testo\Test\Internal;

use Testo\Common\Reflection;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Test;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Accepts files that contain classes or functions with the Test attribute and fetches test cases from them.
 *
 * @internal
 * @psalm-internal Testo\Test
 */
#[InterceptorOptions(testType: TestType::Test)]
final readonly class TestoAttributesLocatorInterceptor implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
        if ($file->path->extension() !== 'php') {
            return $next($file);
        }

        return ($file->getClasses() !== [] || $file->getFunctions() !== []) ? true : $next($file);
    }

    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        # Cases for classes
        foreach ($file->classes as $class) {
            if ($class->isAbstract()) {
                continue;
            }

            $case = $file->cases->define($class, $file, type: TestType::Test);
            $classLevel = Reflection::fetchClassAttributes($class, attributeClass: Test::class) !== [];

            foreach ($case->tests->all() as $member) {
                if ($member->isTest) {
                    continue;
                }

                $method = $member->reflection;
                \assert($method instanceof \ReflectionMethod);

                # With the Test attribute on the class, every public method is a test except potential
                # data providers: only `void` or `never` return types are accepted.
                if ($classLevel && $method->isPublic() && \in_array((string) $method->getReturnType(), ['void', 'never'], true)) {
                    $member->isTest = true;
                    continue;
                }

                if (Reflection::fetchFunctionAttributes($method, attributeClass: Test::class) !== []) {
                    $member->isTest = true;
                }
            }
        }

        if ($file->functions === []) {
            return $next($file);
        }

        # Case for functions
        $case = $file->cases->define(null, $file, type: TestType::Test);
        foreach ($case->tests->all() as $member) {
            if ($member->isTest) {
                continue;
            }

            if (Reflection::fetchFunctionAttributes($member->reflection, attributeClass: Test::class) !== []) {
                $member->isTest = true;
            }
        }

        return $next($file);
    }
}
