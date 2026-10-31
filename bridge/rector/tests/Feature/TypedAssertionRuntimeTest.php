<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Feature;

use Testo\Assert;
use Testo\Bridge\Rector\Set\TestoRectorSetList;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Tests\Bridge\Rector\Feature\Fixture\ConvertedRuntime;
use Tests\Bridge\Rector\Unit\Fixture\RecordingMessenger;

#[Test]
#[CoversNothing]
final class TypedAssertionRuntimeTest
{
    public function weakCallsKeepBindingCoercionOrderAndVoidReturns(): void
    {
        $this->assertFixture('runtime_weak', [
            'WeakStringRuntimeContracts::testNamed' => ['passed', 2],
            'WeakStringRuntimeContracts::testNamedReversed' => ['passed', 2],
            'WeakStringRuntimeContracts::testWeakScalar' => ['passed', 1],
            'WeakStringRuntimeContracts::testPhpDocCannotReplaceNativeType' => ['passed', 1],
            'WeakStringRuntimeContracts::testWeakStringable' => ['passed', 2],
            'WeakStringRuntimeContracts::testPositionalAndNamedMessage' => ['passed', 2],
            'WeakStringRuntimeContracts::testFunctionNamedReversed' => ['passed', 1],
            'WeakStringRuntimeContracts::testSelfAndStatic' => ['passed', 2],
            'WeakStringRuntimeContracts::testWeakMessageScalar' => ['passed', 1],
            'WeakStringRuntimeContracts::testPositionalEvaluationOrder' => ['passed', 2],
            'WeakStringRuntimeContracts::testNecessaryFailure' => ['failed', 1],
            'WeakStringRuntimeContracts::testReturnRemainsNull' => ['passed', 2],
            'WeakStringRuntimeContracts::testPositionalReturnRemainsNull' => ['passed', 4],
        ]);
    }

    public function strictCallsKeepTypeErrorsBeforeAnyAssertion(): void
    {
        $this->assertFixture('runtime_strict', [
            'StrictStringRuntimeContracts::testValidNamed' => ['passed', 1],
            'StrictStringRuntimeContracts::testIntegerMustError' => ['error', 0, \TypeError::class],
            'StrictStringRuntimeContracts::testStringableMustError' => ['error', 0, \TypeError::class],
            'StrictStringRuntimeContracts::testInvalidMessageMustError' => ['error', 0, \TypeError::class],
        ]);
    }

    /**
     * @param array<string, array{string, int, 2?: class-string<\Throwable>}> $expected
     */
    private function assertFixture(string $name, array $expected): void
    {
        $input = (string) \file_get_contents(__DIR__ . '/Fixture/' . $name . '.php.inc');
        $runner = new RectorRunner(new RecordingMessenger(), [], [TestoRectorSetList::PHPUNIT_TO_TESTO]);
        $converted = $runner->convert($input);
        $actual = ConvertedRuntime::run($converted);
        \ksort($actual);
        \ksort($expected);
        Assert::same($actual, $expected);
    }
}
