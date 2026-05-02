<?php

declare(strict_types=1);

namespace Testo\Bench\Internal\Pipeline;

use Testo\Bench;
use Testo\Bench\Internal\BenchHandler;
use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Finds benchmarks defined with the {@see Bench} attribute.
 *
 * @internal
 */
#[InterceptorOptions(order: -20_000, testType: TestType::BenchInline)]
final readonly class BenchFinder implements FileLocatorInterceptor, CaseLocatorInterceptor
{
    /** @var \Closure(TestInfo): mixed Invoker for the test method. */
    private \Closure $handler;

    public function __construct(BenchHandler $handler)
    {
        $this->handler = $handler(...);
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
                if (Reflection::fetchFunctionAttributes($method, attributeClass: Bench::class)) {
                    $case ??= $file->cases->define($class, $file, type: TestType::BenchInline, handler: $this->handler);
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
            if (Reflection::fetchFunctionAttributes($function, attributeClass: Bench::class)) {
                $case ??= $file->cases->define(null, $file, type: TestType::BenchInline, handler: $this->handler);
                $case->tests->define($function);
            }
        }

        return $next($file);
    }
}
