<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/core',
        __DIR__ . '/plugin',
        __DIR__ . '/bridge',
    ])
    ->withSkip([
        __DIR__ . '/bridge/rector',
        __DIR__ . '/bridge/symfony-console/resources/stubs',
        __DIR__ . '/bin',
        '*/tests/*',
        '*/Stub/*',
        '*/Fixture/*',
        // Removing unused public-method parameters breaks implementing classes and callers.
        RemoveUnusedPublicMethodParameterRector::class,
        // RepeatInterceptor uses a closure with use (&$symbols) for batched symbol flushing;
        // Rector's deadCode rules incorrectly remove the body as "unused".
        __DIR__ . '/plugin/repeat/src/Internal/RepeatInterceptor.php',
        // DeferredGenerator uses `return $result; yield;` to create a finished generator —
        // a valid PHP trick that Rector converts to an invalid arrow function.
        __DIR__ . '/plugin/data/src/Internal/DeferredGenerator.php',
        // CompositeException: constructor-promoting $errors makes the constructor's own
        // doc comment awkward to place. Not promoting for now (see #263 review).
        __DIR__ . '/plugin/fiber/src/Exception/CompositeException.php',
        // Filter.php: large refactor surface, deferred until it can be reviewed on its own (#263).
        __DIR__ . '/plugin/filter/Filter.php',
        // DefinitionLocator::functionReflection() looks unused today but is kept intentionally —
        // don't remove "unused" functions from core (#263).
        __DIR__ . '/core/Tokenizer/DefinitionLocator.php',

        // --- Everything below is applied gradually in small follow-up PRs, one rule (or one
        // closely-related cluster of rules) at a time — see #263 review discussion. Each file is
        // removed from this list by the PR that applies its fix. ---

        // ClassPropertyAssignToConstructorPromotionRector
        __DIR__ . '/core/Output/Json/JsonPlugin.php',
        __DIR__ . '/core/Output/Rendering/SharedStream.php',
        __DIR__ . '/core/Testing/Attribute/TestingSuite.php',
        __DIR__ . '/core/Core/Context/Identity.php',

        // MakePropertyReadonlyRector + misc single-file simplifications
        __DIR__ . '/core/Tokenizer/Reflection/TokenizedFile.php',
        __DIR__ . '/core/Application/Application.php',
        __DIR__ . '/plugin/assert/src/Internal/Assertion/AssertJson.php',
        __DIR__ . '/plugin/assert/src/Internal/Assertion/Traits/IterableTrait.php',
        __DIR__ . '/core/Output/Terminal/Renderer/Formatter.php',
        __DIR__ . '/core/Core/Internal/RuntimeSequence.php',

        // Standalone bugfixes (not mechanical Rector output — Rector separately flags an
        // unrelated tag/type finding in these same files; resolved as part of each bugfix PR)
        __DIR__ . '/core/Output/Rendering/ChannelRenderer.php',
        __DIR__ . '/plugin/data/src/Internal/DataProviderInterceptor.php',
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: false,
        typeDeclarations: true,
    );
