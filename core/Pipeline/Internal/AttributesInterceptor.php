<?php

declare(strict_types=1);

namespace Testo\Pipeline\Internal;

use Testo\Common\Reflection;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Pipeline;

/**
 * Reads {@see Interceptable} attributes and integrates them into the pipeline.
 * Also maps the found attributes into the info DTO attributes.
 *
 * @internal
 * @psalm-internal Testo\Pipeline
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ATTRIBUTES)]
final readonly class AttributesInterceptor implements TestRunInterceptor, TestCaseRunInterceptor
{
    public function __construct(
        private InterceptorProvider $interceptorProvider,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $classAttributes = $info->caseInfo->definition->reflection === null
            ? []
            : Reflection::fetchClassAttributes(
                class: $info->caseInfo->definition->reflection,
                attributeClass: Interceptable::class,
                flags: \ReflectionAttribute::IS_INSTANCEOF,
            );

        $methodAttributes = Reflection::fetchFunctionAttributes(
            function: $info->testDefinition->reflection,
            attributeClass: Interceptable::class,
            flags: \ReflectionAttribute::IS_INSTANCEOF,
        );

        $attrs = \array_merge($classAttributes, $methodAttributes);
        if ($attrs === []) {
            # No attributes, continue to next interceptor
            return $next($info);
        }

        $attrs = \array_values(\array_map(
            static function (\ReflectionAttribute $a): Interceptable {
                /** @var Interceptable */
                return $a->newInstance();
            },
            $attrs,
        ));

        # Merge and instantiate attributes
        $interceptors = $this->interceptorProvider->fromAttributes(TestRunInterceptor::class, ...$attrs);
        $info = $info->withAttributes(self::groupAttributes($attrs));

        /** @var callable(TestInfo): TestResult $pipeline */
        $pipeline = $next instanceof Pipeline
            ? $next->combine(...$interceptors)
            : Pipeline::prepare(
                new PipeOptions(includeTypes: [$info->caseInfo->definition->type]),
                ...$interceptors,
            )->with(
                $next,
                /** @see TestRunInterceptor::runTest() */
                'runTest',
            );

        return $pipeline($info);
    }

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $attrs = $info->definition->reflection === null
            ? []
            : Reflection::fetchClassAttributes(
                class: $info->definition->reflection,
                attributeClass: Interceptable::class,
                flags: \ReflectionAttribute::IS_INSTANCEOF,
            );

        if ($attrs === []) {
            # No attributes, continue to next interceptor
            return $next($info);
        }

        $attrs = \array_map(
            static function (\ReflectionAttribute $a): Interceptable {
                /** @var Interceptable */
                return $a->newInstance();
            },
            $attrs,
        );

        # Merge and instantiate attributes
        $interceptors = $this->interceptorProvider->fromAttributes(TestCaseRunInterceptor::class, ...$attrs);
        $info = $info->withAttributes(self::groupAttributes($attrs));

        /** @var callable(CaseInfo): CaseResult $pipeline */
        $pipeline = $next instanceof Pipeline
            ? $next->combine(...$interceptors)
            : Pipeline::prepare(
                new PipeOptions(includeTypes: [$info->definition->type]),
                ...$interceptors,
            )->with(
                $next,
                /** @see TestCaseRunInterceptor::runTestCase() */
                'runTestCase',
            );
        return $pipeline($info);
    }

    /**
     * Converts array of attributes to associative array of attributed lists
     *
     * @param non-empty-list<Interceptable> $attrs
     * @return non-empty-array<class-string<Interceptable>, non-empty-list<Interceptable>>
     */
    private static function groupAttributes(array $attrs): array
    {
        $result = [];
        foreach ($attrs as $attr) {
            $result[$attr::class][] = $attr;
        }

        return $result;
    }
}
