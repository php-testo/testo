<?php

declare(strict_types=1);

namespace Testo\Bench\Middleware;

use Testo\Bench\BenchWith;
use Testo\Bench\Internal\BenchInvoker;
use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Finds benchmarks defined with the {@see BenchWith} attribute.
 */
#[InterceptorOptions(order: -20_000)]
final class BenchFinder implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    /** @var \Closure(TestInfo): mixed Invoker for the test method. */
    private readonly \Closure $invoker;

    public function __construct(BenchInvoker $invoker)
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
                if (Reflection::fetchFunctionAttributes($method, attributeClass: BenchWith::class)) {
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
            if (Reflection::fetchFunctionAttributes($function, attributeClass: BenchWith::class)) {
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
