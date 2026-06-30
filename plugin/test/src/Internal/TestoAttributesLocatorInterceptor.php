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
        # Define cases for classes
        foreach ($file->classes as $class) {
            if ($class->isAbstract()) {
                continue;
            }

            # Check if the class has attribute Test, if so define a case for all its public methods
            if (Reflection::fetchClassAttributes($class, attributeClass: Test::class) !== []) {
                foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    # Skip potential data providers: we accept only public methods with `void` or `never` return type
                    if (\in_array((string) $method->getReturnType(), ['void', 'never'])) {
                        $file->cases->define($class, $file, type: TestType::Test)->tests->define($method);
                    }
                }
            }

            # Otherwise, define a test for each public method with attribute Test
            foreach ($class->getMethods() as $method) {
                if (Reflection::fetchFunctionAttributes($method, attributeClass: Test::class)) {
                    $file->cases->define($class, $file, type: TestType::Test)->tests->define($method);
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
            if (Reflection::fetchFunctionAttributes($function, attributeClass: Test::class)) {
                $case ??= $file->cases->define(null, $file, type: TestType::Test);
                $case->tests->define($function);
            }
        }

        return $next($file);
    }
}
