<?php

declare(strict_types=1);

namespace Testo\Interceptor\Locator;

use Testo\Attribute\Test;
use Testo\Interceptor\CaseLocatorInterceptor;
use Testo\Interceptor\FileLocatorInterceptor;
use Testo\Interceptor\Reflection\Reflection;
use Testo\Module\Tokenizer\Reflection\FileDefinitions;
use Testo\Module\Tokenizer\Reflection\TokenizedFile;
use Testo\Test\Dto\CaseDefinitions;

/**
 * Accepts files that contain classes or functions with the Test attribute and fetches test cases from them.
 */
final class TestoAttributesLocatorInterceptor implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
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

            // Check if the class has attribute Test, if so define a case for all its public methods
            if (Reflection::fetchClassAttributes($class, attributeClass: Test::class) !== []) {
                foreach ($class->getMethods() as $method) {
                    if ($method->isPublic()) {
                        $file->cases->define($class, $file)->tests->define($method);
                    }
                }

                continue;
            }

            // Otherwise, define a test for each public method with attribute Test
            foreach ($class->getMethods() as $method) {
                if ($method->isPublic() && Reflection::fetchFunctionAttributes($method, attributeClass: Test::class)) {
                    $file->cases->define($class, $file)->tests->define($method);
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
                $case ??= $file->cases->define(null, $file);
                $case->tests->define($function);
            }
        }

        return $next($file);
    }
}
