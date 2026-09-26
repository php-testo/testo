<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Testo\Bridge\Rector\PhpunitToTesto\MergeAssertChainRector;
use Testo\Bridge\Rector\TestoPolish\ClassLevelTestAttributeRector;
use Testo\Bridge\Rector\TestoPolish\ExpectExceptionToAttributeRector;
use Testo\Bridge\Rector\TestoPolish\FinalizeTestClassRector;

/**
 * Testo -> Testo polishing set.
 *
 * Tidies tests that already run on Testo, typically right after a migration: return types,
 * `final` test classes, class-level `#[Test]`, the attribute form of a one-statement exception
 * test, and merged assertion pipes. Every rule keeps the set of tests and their outcome unchanged.
 *
 * Scope it to the whole test tree and nothing else: the return-type rules apply to any method in the
 * paths, and `FinalizeTestClassRector` sees only the subclasses within them. Run it as its own pass
 * once the suite is green on Testo, so the class-level rules see the `#[Test]` attributes the
 * conversion set has already placed.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AddVoidReturnTypeWhereNoReturnRector::class);
    $rectorConfig->rule(ReturnNeverTypeRector::class);

    $rectorConfig->rule(FinalizeTestClassRector::class);

    # Before the class-level move, which strips the method attributes this rule tells tests apart by.
    $rectorConfig->rule(ExpectExceptionToAttributeRector::class);
    $rectorConfig->rule(ClassLevelTestAttributeRector::class);

    $rectorConfig->rule(MergeAssertChainRector::class);
};
