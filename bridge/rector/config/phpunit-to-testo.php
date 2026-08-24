<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\PhpunitToTesto\AssertCallToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\CoversClassToCoversRector;
use Testo\Bridge\Rector\PhpunitToTesto\DataProviderAnnotationToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\DataProviderAttributeToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\DoesNotPerformAssertionsToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\ExpectExceptionToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\ExtendsTestCaseToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\GroupToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\LifecycleMethodToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\MarkTestIncompleteRector;
use Testo\Bridge\Rector\PhpunitToTesto\MarkTestSkippedToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\MergeAssertChainRector;
use Testo\Bridge\Rector\PhpunitToTesto\RepeatRetryToTestoRector;
use Testo\Bridge\Rector\PhpunitToTesto\TypedAssertCallToTestoRector;

/**
 * PHPUnit -> Testo conversion set.
 *
 * Primary use case: migrate an existing PHPUnit test suite onto Testo. Registers
 * only the rules that perform a faithful, automatic conversion. Conversions that
 * are unconvertible or too fragile to automate are shipped as documented stub
 * rules and are intentionally NOT registered here — see
 * bridge/rector/src/PhpunitToTesto/TODO.md and bridge/rector/README.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AssertCallToTestoRector::class);

    # Assertions that map onto a typed Assert head + matcher (comparisons, array keys, canonicalizing,
    # emptiness) rather than a flat facade call — see TypedAssertCallToTestoRector.
    $rectorConfig->rule(TypedAssertCallToTestoRector::class);

    $rectorConfig->rule(MarkTestSkippedToTestoRector::class);

    # Incomplete has no exact Testo status; mapped to a Skipped throw with an "Incomplete:" reason
    # prefix so the nuance survives (lossy — see MarkTestIncompleteRector / TODO.md).
    $rectorConfig->rule(MarkTestIncompleteRector::class);
    $rectorConfig->rule(CoversClassToCoversRector::class);
    $rectorConfig->rule(LifecycleMethodToTestoRector::class);
    $rectorConfig->rule(ExpectExceptionToTestoRector::class);

    # Data providers — both source forms map straight to Testo's attribute, with no dependency on
    # PHPUnit being installed: the docblock annotation and the PHPUnit attribute each convert directly.
    $rectorConfig->rule(DataProviderAnnotationToTestoRector::class);
    $rectorConfig->rule(DataProviderAttributeToTestoRector::class);

    # Groups: collapse repeated `@group` / `#[Group]` into one variadic Testo `#[Group(...)]`.
    $rectorConfig->rule(GroupToTestoRector::class);

    $rectorConfig->rule(DoesNotPerformAssertionsToTestoRector::class);

    # Structural: detach from PHPUnit's TestCase base class and make discovery attribute-based.
    $rectorConfig->rule(ExtendsTestCaseToTestoRector::class);

    # Cleanup pass: collapse adjacent `Assert::<type>($var)->…` chains on the same variable into one.
    $rectorConfig->rule(MergeAssertChainRector::class);

    # Repeat/Retry method attributes (PHPUnit 13.3+) map onto Testo's #[Repeat]/#[Retry].
    $rectorConfig->rule(RepeatRetryToTestoRector::class);
};
