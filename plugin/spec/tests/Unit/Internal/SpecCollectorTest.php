<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Spec\Internal\SpecCollector;
use Testo\Spec\Internal\SpecInterceptor;
use Testo\Test;

#[Test]
#[Covers(SpecCollector::class)]
final class SpecCollectorTest
{
    public function accumulatesFragmentsAcrossSuites(): void
    {
        $collector = self::collector(__FUNCTION__);
        $collector->addSuite(self::suite([
            self::test('Checkout [test]', 'tax', 'Tax is included.', sectionNumber: '5', sectionTitle: 'Checkout'),
        ]));
        $collector->addSuite(self::suite([
            self::test('Auth [test]', 'login', 'A user logs in.', sectionNumber: '2', sectionTitle: 'Auth'),
        ]));

        $content = (string) \file_get_contents((string) $collector->flush());
        Assert::true(\str_contains($content, '# 2. Auth'));
        Assert::true(\str_contains($content, '# 5. Checkout'));
    }

    public function ignoresTestsWithoutSpecMessages(): void
    {
        $collector = self::collector(__FUNCTION__);
        $collector->addSuite(self::suite([new TestResult(info: self::info(), status: Status::Passed)]));

        Assert::null($collector->flush());
    }

    public function flushReturnsNullWhenNothingCollected(): void
    {
        Assert::null(self::collector(__FUNCTION__)->flush());
    }

    public function writesASingleDocument(): void
    {
        $collector = self::collector(__FUNCTION__);
        $collector->addSuite(self::suite([
            self::test('Checkout [test]', 'tax', 'Tax is included.', sectionNumber: '5', sectionTitle: 'Checkout'),
        ]));

        $path = $collector->flush();

        Assert::notNull($path);
        Assert::true(\str_ends_with((string) $path, 'spec.md'));
        $content = (string) \file_get_contents((string) $path);
        Assert::true(\str_contains($content, '# 5. Checkout'));
        Assert::true(\str_contains($content, '## 5.1 tax'));
    }

    public function rendersNumberedSections(): void
    {
        $model = [
            'sections' => [
                ['number' => '5', 'title' => 'Checkout', 'items' => [
                    ['number' => '5.1', 'title' => 'tax', 'story' => 'Tax is included.', 'tags' => ['checkout']],
                ]],
            ],
            'extra' => [],
        ];

        $out = self::collector(__FUNCTION__)->render($model);

        Assert::true(\str_contains($out, '# 5. Checkout'));
        Assert::true(\str_contains($out, "## 5.1 tax\n\nTax is included."));
        Assert::true(\str_contains($out, '`checkout`'));
    }

    public function rendersUncategorizedTailWithBulletsAndParagraphs(): void
    {
        $model = [
            'sections' => [],
            'extra' => [
                ['title' => 'Notes', 'items' => [
                    ['title' => 'A side note', 'story' => 'Noted behaviour.', 'tags' => []],
                    ['title' => null, 'story' => 'Plain behaviour.', 'tags' => []],
                ]],
            ],
        ];

        $out = self::collector(__FUNCTION__)->render($model);

        Assert::true(\str_contains($out, '# Uncategorized'));
        Assert::true(\str_contains($out, '## Notes'));
        Assert::true(\str_contains($out, "- A side note\n  Noted behaviour."));
        Assert::true(\str_contains($out, "\nPlain behaviour.\n"));
    }

    private static function test(
        string $case,
        string $name,
        string $story,
        ?string $sectionNumber = null,
        ?string $sectionTitle = null,
    ): TestResult {
        $message = new Message(
            time: 0.0,
            channel: SpecInterceptor::CHANNEL,
            level: Level::Info,
            content: "### {$name}\n\n{$story}\n",
            context: [
                'title' => null,
                'story' => $story,
                'tags' => [],
                'number' => null,
                'sectionTitle' => $sectionTitle,
                'sectionNumber' => $sectionNumber,
                'line' => 1,
                'test' => $name,
                'case' => $case,
            ],
        );

        return new TestResult(info: self::info($name), status: Status::Passed, messages: new MessageLog([$message]));
    }

    /**
     * @param list<TestResult> $tests
     */
    private static function suite(array $tests): SuiteResult
    {
        return new SuiteResult([new CaseResult($tests, Status::Passed)], Status::Passed);
    }

    private static function info(string $name = 'test'): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, 'info');
        $caseInfo = new CaseInfo(definition: new CaseDefinition(name: 'TestCase', type: 'test'));

        return new TestInfo(
            name: $name,
            caseInfo: $caseInfo,
            testDefinition: new TestDefinition(reflection: $reflection),
        );
    }

    private static function collector(string $name): SpecCollector
    {
        $dir = __DIR__ . '/../../runtime/' . $name;
        if (\is_dir($dir)) {
            foreach ((array) \glob($dir . '/*.md') as $file) {
                \is_string($file) and @\unlink($file);
            }
        }

        return new SpecCollector($dir);
    }
}
