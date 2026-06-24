<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit\Reflection;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Test;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

#[Test]
#[Covers(FileDefinitions::class)]
final class FileDefinitionsTest
{
    public function constructorStoresTokenizedFileAndDefaultsAreEmpty(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        $def = new FileDefinitions($tokenized);

        Assert::same($tokenized, $def->tokenizedFile);
        Assert::same([], $def->classes);
        Assert::same([], $def->interfaces);
        Assert::same([], $def->enums);
        Assert::same([], $def->functions);
        Assert::same([], $def->traits);
    }

    public function constructorStoresExplicitlyPassedValues(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');
        $cases = new CaseDefinitions();
        $classes = ['Foo' => new \ReflectionClass(\stdClass::class)];

        $def = new FileDefinitions(
            tokenizedFile: $tokenized,
            cases: $cases,
            classes: $classes,
        );

        Assert::same($tokenized, $def->tokenizedFile);
        Assert::same($cases, $def->cases);
        Assert::same($classes, $def->classes);
    }

    private static function tokenize(string $stub): TokenizedFile
    {
        $path = __DIR__ . '/../../Stub/' . $stub;
        return new TokenizedFile(new \SplFileInfo($path), $path);
    }
}
