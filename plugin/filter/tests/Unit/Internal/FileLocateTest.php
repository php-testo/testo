<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Test;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Verifies Stage 1 ({@see FilterInterceptor::locateFile()} / matchFile) — the token-based
 * pre-filter that decides whether a file is worth loading for reflection. Matching is by
 * class name, method `Class::method`, or bare fragment, anchored with `\b...$`.
 */
#[Test]
#[Covers(FilterInterceptor::class)]
final class FileLocateTest
{
    private const FIXTURE = __DIR__ . '/../Fixture/GroupedTestClass.php';

    public function matchesByMethodFragment(): void
    {
        Assert::true($this->locate(['dbTest']));
    }

    public function matchesByClassName(): void
    {
        Assert::true($this->locate(['GroupedTestClass']));
    }

    public function matchesByClassMethodFormat(): void
    {
        Assert::true($this->locate(['GroupedTestClass::slowTest']));
    }

    public function doesNotMatchUnknownName(): void
    {
        Assert::false($this->locate(['noSuchSymbol']));
    }

    public function prefixDoesNotMatchBecauseOfEndAnchor(): void
    {
        Assert::false($this->locate(['dbTes']));
    }

    public function midWordSuffixDoesNotMatchBecauseOfWordBoundary(): void
    {
        Assert::false($this->locate(['bTest']));
    }

    public function regexMetacharactersAreEscaped(): void
    {
        # `db.est` must be treated literally (preg_quote), so it must not match dbTest.
        Assert::false($this->locate(['db.est']));
    }

    public function noFilterPassesThrough(): void
    {
        # With no name filters the pre-filter must not reject the file.
        Assert::true($this->locate([]));
    }

    /**
     * Run Stage 1 against the fixture file and return the locator decision.
     *
     * @param list<non-empty-string> $names
     */
    private function locate(array $names): bool
    {
        $interceptor = new FilterInterceptor(new Filter(names: $names));
        $file = new TokenizedFile(file: new \SplFileInfo(self::FIXTURE), path: self::FIXTURE);

        return (bool) $interceptor->locateFile($file, static fn(TokenizedFile $f): bool => true);
    }
}
