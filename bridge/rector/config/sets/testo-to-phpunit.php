<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\TestoToPhpunit\AssertCallToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\ConstructorDestructorToLifecycleRector;
use Testo\Bridge\Rector\TestoToPhpunit\CoversToCoversClassRector;
use Testo\Bridge\Rector\TestoToPhpunit\DataProviderToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\ExpectExceptionAttributeToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\ExpectExceptionToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\ExpectNoAssertionsToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\GroupInheritanceToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\GroupToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\LifecycleAttributesToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\TestClassToTestCaseRector;
use Testo\Bridge\Rector\TestoToPhpunit\ThrowSkipTestToPhpUnitRector;
use Testo\Bridge\Rector\TestoToPhpunit\TypedAssertChainRector;

/**
 * Testo -> PHPUnit conversion set.
 *
 * Primary use case: run mutation testing with a runner (PHPUnit) that shares no
 * code with the mutated engine, so mutating Testo's own pipeline cannot break
 * test discovery. See bridge/rector/README.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AssertCallToPhpUnitRector::class);
    $rectorConfig->rule(ThrowSkipTestToPhpUnitRector::class);
    $rectorConfig->rule(CoversToCoversClassRector::class);
    $rectorConfig->rule(LifecycleAttributesToPhpUnitRector::class);
    $rectorConfig->rule(ExpectExceptionToPhpUnitRector::class);
    $rectorConfig->rule(ExpectExceptionAttributeToPhpUnitRector::class);
    $rectorConfig->rule(TypedAssertChainRector::class);
    $rectorConfig->rule(GroupToPhpUnitRector::class);
    $rectorConfig->rule(GroupInheritanceToPhpUnitRector::class);
    $rectorConfig->rule(ExpectNoAssertionsToPhpUnitRector::class);
    $rectorConfig->rule(DataProviderToPhpUnitRector::class);

    # Structural: attach PHPUnit's TestCase base class and convert #[\Testo\Test] discovery.
    $rectorConfig->rule(TestClassToTestCaseRector::class);

    # Runs after the class is a TestCase: turn a parameterless __construct()/__destruct() into
    # #[Before]/#[After] lifecycle methods.
    $rectorConfig->rule(ConstructorDestructorToLifecycleRector::class);
};
