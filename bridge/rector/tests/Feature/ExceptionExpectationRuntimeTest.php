<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Bridge\Rector\PhpunitToTesto\ExpectExceptionToTestoRector;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Bridge\Rector\TestoToPhpunit\ExpectExceptionToPhpUnitRector;
use Testo\Codecov\CoversNothing;
use Testo\Core\Value\Status;
use Testo\Output\ConsoleStreams;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\RecordingMessenger;

#[Test]
#[CoversNothing]
final class ExceptionExpectationRuntimeTest
{
    public function convertedExpectationsKeepTheirVerdictsAndEvaluationOrder(): void
    {
        $fixture = \dirname(__DIR__, 2) . '/src/PhpunitToTesto/ExpectExceptionToTestoRector/runtime_contracts.php.inc';
        [$input] = \explode("-----\n", \str_replace("\r\n", "\n", (string) \file_get_contents($fixture)), 2);
        $runner = new RectorRunner(new RecordingMessenger(), [ExpectExceptionToTestoRector::class]);
        $converted = $runner->convert($input);

        $this->assertResults($converted, [
            'emptyMustFail' => [Status::Failed, 1],
            'emptyPasses' => [Status::Passed, 1],
            'lastMessageWins' => [Status::Passed, 1],
            'namedArguments' => [Status::Passed, 1],
            'literalRegexCharacters' => [Status::Passed, 1],
            'dynamicEmptyMustFail' => [Status::Failed, 1],
            'evaluationOrder' => [Status::Passed, 2],
            'overwrittenArgumentThrows' => [Status::Passed, 1],
            'independentPattern' => [Status::Passed, 1],
            'independentPatternMustFail' => [Status::Failed, 1],
            'assignmentKeepsSnapshots' => [Status::Passed, 1],
            'temporaryDoesNotClobberSource' => [Status::Passed, 2],
            'longRepeatedMessage' => [Status::Passed, 1],
            'substringMismatchMustFail' => [Status::Failed, 1],
            'lastEmptyWins' => [Status::Passed, 1],
            'lastNonemptyWins' => [Status::Passed, 1],
            'dynamicMessageAndPattern' => [Status::Passed, 1],
            'repeatedMessageAndPatternMustFail' => [Status::Failed, 1],
            'conditionalLocalSurvives' => [Status::Passed, 2],
            'laterLocalSurvives' => [Status::Passed, 2],
            'assignmentCanThrow' => [Status::Passed, 1],
            'weakFalseMustFail' => [Status::Failed, 1],
            'overwrittenMessageMustFail' => [Status::Failed, 1],
        ]);
    }

    public function strictMessageCoercionStillThrows(): void
    {
        $fixture = \dirname(__DIR__, 2) . '/src/PhpunitToTesto/ExpectExceptionToTestoRector/strict_message_coercion.php.inc';
        [$input] = \explode("-----\n", \str_replace("\r\n", "\n", (string) \file_get_contents($fixture)), 2);
        $runner = new RectorRunner(new RecordingMessenger(), [ExpectExceptionToTestoRector::class]);

        $this->assertResults($runner->convert($input), ['invalidStringStillThrows' => [Status::Passed, 1]]);
    }

    public function repeatedMessageModesKeepTheirVerdictsInReverse(): void
    {
        $fixture = \dirname(__DIR__, 2) . '/src/TestoToPhpunit/ExpectExceptionToPhpUnitRector/repeated_message_modes_unchanged.php.inc';
        $runner = new RectorRunner(new RecordingMessenger(), [ExpectExceptionToPhpUnitRector::class]);

        $this->assertResults($runner->convert((string) \file_get_contents($fixture)), [
            'emptySubstringWins' => [Status::Passed, 1],
            'exactMessageWins' => [Status::Passed, 1],
            'emptyModeChanges' => [Status::Passed, 1],
        ]);
    }

    /**
     * @param array<string, array{Status, int}> $expected
     */
    private function assertResults(string $converted, array $expected): void
    {
        $file = \sys_get_temp_dir() . '/testo-converted-' . \bin2hex(\random_bytes(8)) . '.php';
        $sink = \fopen('php://memory', 'w+b');
        try {
            \file_put_contents($file, $converted);
            $app = Application::createFromConfig(new ApplicationConfig(
                suites: [new SuiteConfig('Converted', location: new FinderConfig(include: [$file]))],
            ));
            $app->getContainer()->set(new ConsoleStreams($sink, $sink), ConsoleStreams::class);
            $result = $app->run();
        } finally {
            \fclose($sink);
            @\unlink($file);
        }

        $actual = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $actual[$test->info->name] = [$test->status, $test->summary->metric('assertions')];
                }
            }
        }
        \ksort($actual);
        \ksort($expected);
        Assert::same($actual, $expected);
    }
}
