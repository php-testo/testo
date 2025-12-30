<?php

declare(strict_types=1);

namespace Testo\Sample\Internal;

use Testo\Interceptor\CaseLocatorInterceptor;
use Testo\Interceptor\FileLocatorInterceptor;
use Testo\Interceptor\Reflection\Reflection;
use Testo\Module\Tokenizer\Reflection\FileDefinitions;
use Testo\Module\Tokenizer\Reflection\TokenizedFile;
use Testo\Sample\TestInline;
use Testo\Test\Dto\CaseDefinitions;
use Testo\Test\Dto\TestInfo;

/**
 * Finds inline tests defined with the {@see TestInline} attribute.
 */
final class TestInlineFinder implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    /** @var \Closure(TestInfo): mixed Invoker for the test method. */
    private readonly \Closure $invoker;

    public function __construct(InlineTestInvoker $invoker)
    {
        $this->invoker = $invoker(...);
    }

    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
        return $file->getClasses() !== [] || $file->getFunctions() !== [] ? true : $next($file);
    }

    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        // Define cases for classes
        foreach ($file->classes as $class) {
            if ($class->isAbstract()) {
                continue;
            }

            $case = null;
            foreach ($class->getMethods() as $method) {
                if (Reflection::fetchFunctionAttributes($method, attributeClass: TestInline::class)) {
                    if ($case === null) {
                        $case = $file->cases->define($class, $file);
                        $case->invoker = $this->invoker;
                    }

                    $case->tests->define($method);
                }
            }
        }

        if ($file->functions === []) {
            return $next($file);
        }

        // Define a case for functions
        // Implement a lazy case definition
        $case = null;
        foreach ($file->functions as $function) {
            if (Reflection::fetchFunctionAttributes($function, attributeClass: TestInline::class)) {
                if ($case === null) {
                    $case = $file->cases->define(null, $file);
                    $case->invoker = $this->invoker;
                }

                $case->tests->define($function);
            }
        }

        return $next($file);
    }
}
