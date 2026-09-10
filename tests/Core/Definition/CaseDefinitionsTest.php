<?php

declare(strict_types=1);

namespace Tests\Core\Definition;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Test;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Case creation seeds the case with all its candidate members as non-tests, so that finders promote
 * the ones they recognise and the rest stay reachable (lifecycle hooks, helpers).
 */
#[Test]
#[Covers(CaseDefinitions::class)]
final class CaseDefinitionsTest
{
    public function classCaseIsPrefilledWithEveryMethodIncludingInheritedAsNonTests(): void
    {
        $file = $this->fileDefinitions();

        $case = $file->cases->define(new \ReflectionClass(PrefillChild::class), $file, type: TestType::Test);

        Assert::array($case->tests->getTests())->hasCount(0);
        Assert::array($case->tests->filter(isTest: false))
            ->hasKeys('ownMethod', 'inheritedMethod', 'inheritedStaticMethod');
    }

    public function functionCaseIsPrefilledWithEveryFreeFunctionAsNonTest(): void
    {
        $file = $this->fileDefinitions([
            'strlen' => new \ReflectionFunction('strlen'),
            'trim' => new \ReflectionFunction('trim'),
        ]);

        $case = $file->cases->define(null, $file, type: TestType::Test);

        Assert::array($case->tests->getTests())->hasCount(0);
        Assert::array($case->tests->filter(isTest: false))->hasKeys('strlen', 'trim');
    }

    /**
     * A finder promotes the members it recognises with a plain test registration; the others remain
     * non-tests.
     */
    public function finderPromotesPrefilledMembersToTests(): void
    {
        $file = $this->fileDefinitions();
        $case = $file->cases->define(new \ReflectionClass(PrefillChild::class), $file, type: TestType::Test);

        $case->tests->define(new \ReflectionMethod(PrefillChild::class, 'ownMethod'));

        Assert::array($case->tests->getTests())->hasKeys('ownMethod')->hasCount(1);
        Assert::array($case->tests->filter(isTest: false))
            ->hasKeys('inheritedMethod', 'inheritedStaticMethod')
            ->doesNotHaveKeys('ownMethod');
    }

    /**
     * Finders whose tests are the only members of interest opt out of the seeding.
     */
    public function prefillCanBeDisabled(): void
    {
        $file = $this->fileDefinitions(['strlen' => new \ReflectionFunction('strlen')]);

        $classCase = $file->cases->define(new \ReflectionClass(PrefillChild::class), $file, type: TestType::TestInline, prefill: false);
        $functionCase = $file->cases->define(null, $file, type: TestType::TestInline, prefill: false);

        Assert::array($classCase->tests->all())->hasCount(0);
        Assert::array($functionCase->tests->all())->hasCount(0);
    }

    /**
     * Defining the same class (the same reflection instance, as finders share the ones the file
     * carries) for the same type again returns the existing case: the seeding happens once, and
     * flags set in between survive.
     */
    public function redefiningReturnsTheSameCaseWithoutReseeding(): void
    {
        $file = $this->fileDefinitions();
        $class = new \ReflectionClass(PrefillChild::class);
        $first = $file->cases->define($class, $file, type: TestType::Test);
        $first->tests->define(new \ReflectionMethod(PrefillChild::class, 'ownMethod'));

        $second = $file->cases->define($class, $file, type: TestType::Test);

        Assert::same($second, $first);
        Assert::array($second->tests->getTests())->hasKeys('ownMethod');
    }

    /**
     * @param array<string, \ReflectionFunction> $functions
     */
    private function fileDefinitions(array $functions = []): FileDefinitions
    {
        $tokenized = new TokenizedFile(file: new \SplFileInfo(__FILE__), path: __FILE__);

        return new FileDefinitions($tokenized, functions: $functions);
    }
}

abstract class PrefillParent
{
    public function inheritedMethod(): void {}

    public static function inheritedStaticMethod(): void {}
}

final class PrefillChild extends PrefillParent
{
    public function ownMethod(): void {}
}
