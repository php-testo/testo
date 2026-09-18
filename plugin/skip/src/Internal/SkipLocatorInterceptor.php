<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

use Testo\Common\Reflection;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Skip;
use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Flags the `#[Skip]`-annotated tests as {@see \Testo\Core\Definition\TestDefinition::$skipped}
 * once the cases of a file are located.
 *
 * The flag alone: the reason travels with the attribute, to {@see SkipInterceptor}.
 *
 * A class-level attribute is inherited from parents and traits, a method-level one from the
 * overridden method — {@see Reflection} walks both chains by default.
 *
 * `testType: TestType::Test` only drops this interceptor from a run filtered to other types; a
 * located file still yields cases of every type, hence the per-case check that keeps `#[Skip]`
 * inert outside a plain test.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
#[InterceptorOptions(testType: TestType::Test)]
final readonly class SkipLocatorInterceptor implements CaseLocatorInterceptor
{
    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        /** @var CaseDefinitions $result */
        $result = $next($file);

        foreach ($result->getCases() as $case) {
            if ($case->type !== TestType::Test->value) {
                continue;
            }

            $classSkipped = $case->reflection !== null
                && Reflection::fetchClassAttributes($case->reflection, attributeClass: Skip::class, limit: 1) !== [];

            foreach ($case->tests->getTests(active: null) as $test) {
                $classSkipped
                    || Reflection::fetchFunctionAttributes($test->reflection, attributeClass: Skip::class, limit: 1) !== []
                    and $test->skipped = true;
            }
        }

        return $result;
    }
}
