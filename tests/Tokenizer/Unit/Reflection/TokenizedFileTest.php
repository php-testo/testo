<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit\Reflection;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Tokenizer\Reflection\TokenizedFile;

#[Test]
#[Covers(TokenizedFile::class)]
final class TokenizedFileTest
{
    public function methodsFqnIncludesAllVisibilityVariants(): void
    {
        $methods = self::tokenize('ClassWithMethods.php')->getMethodsFQN();

        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\ClassWithMethods::__construct',
            'Tests\Tokenizer\Stub\ClassWithMethods::__invoke',
            'Tests\Tokenizer\Stub\ClassWithMethods::privateMethod',
            'Tests\Tokenizer\Stub\ClassWithMethods::protectedMethod',
            'Tests\Tokenizer\Stub\ClassWithMethods::publicMethod',
            'Tests\Tokenizer\Stub\ClassWithMethods::staticMethod',
        ]);
    }

    public function methodsFqnIsEmptyForClassWithoutMethods(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        Assert::same($tokenized->getClasses(), ['Tests\Tokenizer\Stub\EmptyClass']);
        Assert::same($tokenized->getMethodsFQN(), []);
    }

    public function methodsFqnIsEmptyForFileWithOnlyFreeFunctions(): void
    {
        $tokenized = self::tokenize('OnlyFunctions.php');

        Assert::same($tokenized->getMethodsFQN(), []);
        $functions = $tokenized->getFunctions();
        \sort($functions);
        Assert::same($functions, [
            'Tests\Tokenizer\Stub\freeFunctionOne',
            'Tests\Tokenizer\Stub\freeFunctionTwo',
        ]);
    }

    public function methodsFqnDoesNotIncludeFreeFunctionsInMixedFile(): void
    {
        $tokenized = self::tokenize('ClassAndFreeFunction.php');

        Assert::same($tokenized->getMethodsFQN(), [
            'Tests\Tokenizer\Stub\ClassWithSingleMethod::aMethod',
        ]);
        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\aFreeFunction',
        ]);
    }

    public function methodsFqnHandlesMultipleClassesInOneFile(): void
    {
        $methods = self::tokenize('TwoClassesInOneFile.php')->getMethodsFQN();

        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\FirstClass::alpha',
            'Tests\Tokenizer\Stub\FirstClass::beta',
            'Tests\Tokenizer\Stub\SecondClass::gamma',
        ]);
    }

    public function methodsFqnIncludesTraitAndInterfaceMethods(): void
    {
        $methods = self::tokenize('TraitAndInterface.php')->getMethodsFQN();

        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\MyInterface::fromInterface',
            'Tests\Tokenizer\Stub\MyTrait::fromTrait',
        ]);
    }

    public function methodsFqnHandlesGlobalNamespaceClasses(): void
    {
        $methods = self::tokenize('NoNamespace/GlobalClass.php')->getMethodsFQN();

        Assert::same($methods, ['TokenizerStubGlobalClass::foo']);
    }

    public function topLevelClosuresAreNotRegisteredAsFreeFunctions(): void
    {
        $tokenized = self::tokenize('ClosuresInFreeFunctionScope.php');

        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\realFreeFunction',
        ]);
        Assert::same($tokenized->getMethodsFQN(), []);
    }

    public function closuresInsideMethodsDoNotLeakIntoDeclarations(): void
    {
        $tokenized = self::tokenize('ClosuresInsideMethods.php');

        $methods = $tokenized->getMethodsFQN();
        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\UsesClosures::build',
            'Tests\Tokenizer\Stub\UsesClosures::map',
        ]);
        Assert::same($tokenized->getFunctions(), []);
    }

    public function attributedAnonymousFunctionIsSkippedButNamedOneIsKept(): void
    {
        $tokenized = self::tokenize('AttributedClosure.php');

        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\namedWithAttribute',
        ]);
        Assert::same($tokenized->getMethodsFQN(), []);
    }

    public function anonymousClassMethodsAtFileScopeDoNotLeakIntoFunctions(): void
    {
        $tokenized = self::tokenize('AnonymousClassInFreeScope.php');

        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\freeAlongsideAnon',
        ]);
        Assert::same($tokenized->getMethodsFQN(), []);
        Assert::same($tokenized->getClasses(), []);
    }

    public function anonymousClassMethodsNestedInDeclarationsAreNotMisattributed(): void
    {
        $tokenized = self::tokenize('AnonymousClassNestedInDeclarations.php');

        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\makesAnon',
        ]);
        $methods = $tokenized->getMethodsFQN();
        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\HostsAnon::make',
            'Tests\Tokenizer\Stub\HostsAnon::plainMethod',
        ]);
        Assert::same($tokenized->getClasses(), ['Tests\Tokenizer\Stub\HostsAnon']);
    }

    public function anonymousClassWithAttributeIsStillAnonymous(): void
    {
        $tokenized = self::tokenize('NewWithAttributeAnonymousClass.php');

        Assert::same($tokenized->getFunctions(), []);
        Assert::same($tokenized->getMethodsFQN(), []);
        Assert::same($tokenized->getClasses(), []);
    }

    public function declarationsAreScopedToTheirBracedNamespace(): void
    {
        $tokenized = self::tokenize('BracedNamespaces.php');

        $functions = $tokenized->getFunctions();
        \sort($functions);
        Assert::same($functions, [
            'Tests\Tokenizer\Stub\First\alpha',
            'Tests\Tokenizer\Stub\Second\beta',
        ]);

        $methods = $tokenized->getMethodsFQN();
        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\First\FirstClass::fa',
            'Tests\Tokenizer\Stub\Second\SecondClass::sb',
        ]);

        $classes = $tokenized->getClasses();
        \sort($classes);
        Assert::same($classes, [
            'Tests\Tokenizer\Stub\First\FirstClass',
            'Tests\Tokenizer\Stub\Second\SecondClass',
        ]);
    }

    public function keywordNamedMethodsAreDetectedByPosition(): void
    {
        $tokenized = self::tokenize('KeywordNamedDeclarations.php');

        $methods = $tokenized->getMethodsFQN();
        \sort($methods);
        Assert::same($methods, [
            'Tests\Tokenizer\Stub\KeywordNames::list',
            'Tests\Tokenizer\Stub\KeywordNames::print',
            'Tests\Tokenizer\Stub\KeywordNames::unset',
        ]);
        Assert::same($tokenized->getFunctions(), []);
    }

    public function nestedAnonymousClassBoundariesAreResolvedCorrectly(): void
    {
        $tokenized = self::tokenize('NestedAnonymousClasses.php');

        Assert::same($tokenized->getFunctions(), [
            'Tests\Tokenizer\Stub\afterParentAnon',
        ]);
        Assert::same($tokenized->getMethodsFQN(), [
            'Tests\Tokenizer\Stub\RealAfterAnon::realMethod',
        ]);
        Assert::same($tokenized->getClasses(), ['Tests\Tokenizer\Stub\RealAfterAnon']);
    }

    // --- getEnums ---

    public function getEnumsReturnsEmptyWhenNoEnumsDeclared(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        Assert::same($tokenized->getEnums(), []);
    }

    public function getEnumsReturnsAllDeclaredEnumNames(): void
    {
        $tokenized = self::tokenize('EnumDeclaration.php');

        $enums = $tokenized->getEnums();
        \sort($enums);
        Assert::same($enums, [
            'Tests\Tokenizer\Stub\Color',
            'Tests\Tokenizer\Stub\Status',
        ]);
    }

    // --- getTraits ---

    public function getTraitsReturnsEmptyWhenNoTraitsDeclared(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        Assert::same($tokenized->getTraits(), []);
    }

    public function getTraitsReturnsDeclaredTraitNames(): void
    {
        $tokenized = self::tokenize('TraitAndInterface.php');

        Assert::same($tokenized->getTraits(), ['Tests\Tokenizer\Stub\MyTrait']);
    }

    // --- getInterfaces ---

    public function getInterfacesReturnsEmptyWhenNoInterfacesDeclared(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        Assert::same($tokenized->getInterfaces(), []);
    }

    public function getInterfacesReturnsDeclaredInterfaceNames(): void
    {
        $tokenized = self::tokenize('TraitAndInterface.php');

        Assert::same($tokenized->getInterfaces(), ['Tests\Tokenizer\Stub\MyInterface']);
    }

    // --- hasIncludes ---

    public function hasIncludesIsFalseForPlainClassFile(): void
    {
        $tokenized = self::tokenize('EmptyClass.php');

        Assert::false($tokenized->hasIncludes);
    }

    public function hasIncludesIsTrueWhenFileContainsRequireOrInclude(): void
    {
        $tokenized = self::tokenize('FileWithIncludes.php');

        Assert::true($tokenized->hasIncludes);
    }

    // --- exportSchema / importSchema ---

    public function exportSchemaReturnsHasIncludesDeclarationsFunctionsAndNamespaces(): void
    {
        $tokenized = self::tokenize('ClassWithMethods.php');

        $schema = $tokenized->exportSchema();

        Assert::same($schema[0], false);
        Assert::true(isset($schema[1]['T_CLASS']['Tests\Tokenizer\Stub\ClassWithMethods']));
        Assert::same($schema[2], []);
        Assert::true(isset($schema[3]['Tests\Tokenizer\Stub']));
    }

    // --- use imports (registerUse) ---

    public function useImportsAreRecordedInNamespaceUses(): void
    {
        $tokenized = self::tokenize('FileWithUseImports.php');

        $schema = $tokenized->exportSchema();
        $uses = $schema[3]['Tests\Tokenizer\Stub'][TokenizedFile::N_USES];

        Assert::same($uses['ArrayObject'], 'ArrayObject');
        Assert::same($uses['StdAlias'], 'stdClass');
    }

    public function useFunctionImportsDoNotCreateFunctionDeclarations(): void
    {
        $tokenized = self::tokenize('UseFunctionImport.php');

        Assert::same($tokenized->getFunctions(), ['Tests\Tokenizer\Stub\realDeclaredFunction']);
    }

    // --- named-param class keyword (isCorrectDeclaration returning false) ---

    public function namedParamClassKeywordIsNotMistakenForClassDeclaration(): void
    {
        $tokenized = self::tokenize('NamedParamClassKeyword.php');

        Assert::same($tokenized->getClasses(), ['Tests\Tokenizer\Stub\NamedParamClassKeyword']);
    }

    // --- getInvocations ---

    public function getInvocationsReturnsSelfAndStaticCallsResolvedToCurrentClass(): void
    {
        $tokenized = self::tokenize('InvocationsStatic.php');

        $invocations = $tokenized->getInvocations();

        Assert::same(\count($invocations), 3);

        $names = \array_map(static fn($inv) => $inv->name, $invocations);
        Assert::true(\in_array('staticHelper', $names, true));
        Assert::true(\in_array('anotherHelper', $names, true));
        Assert::true(\in_array('instanceMethod', $names, true));

        foreach ($invocations as $inv) {
            Assert::same($inv->class, 'Tests\Tokenizer\Stub\InvocationsStatic');
            Assert::true($inv->isMethod());
        }
    }

    public function getInvocationsResolvesThisCallsToCurrentClass(): void
    {
        $tokenized = self::tokenize('InvocationsStatic.php');

        $invocations = $tokenized->getInvocations();
        $thisCall = null;
        foreach ($invocations as $inv) {
            if ($inv->name === 'instanceMethod') {
                $thisCall = $inv;
            }
        }

        Assert::same($thisCall?->operator, '->');
        Assert::same($thisCall?->class, 'Tests\Tokenizer\Stub\InvocationsStatic');
    }

    public function getInvocationsDetectsExternalStaticCall(): void
    {
        $tokenized = self::tokenize('InvocationsFreeFunction.php');

        $invocations = $tokenized->getInvocations();

        Assert::same(\count($invocations), 1);
        Assert::same($invocations[0]->class, 'SomeClass');
        Assert::same($invocations[0]->operator, '::');
        Assert::same($invocations[0]->name, 'staticCall');
        Assert::same($invocations[0]->level, 0);
    }

    public function getInvocationsAssignsCorrectNestingLevelToNestedCalls(): void
    {
        $tokenized = self::tokenize('InvocationsNested.php');

        $invocations = $tokenized->getInvocations();

        $nested = \array_filter($invocations, static fn($inv) => $inv->level === 1);
        $top    = \array_filter($invocations, static fn($inv) => $inv->level === 0);

        Assert::same(\count($nested), 2);
        Assert::same(\count($top), 2);
    }

    public function getInvocationsIsIdempotentOnSecondCall(): void
    {
        $tokenized = self::tokenize('InvocationsStatic.php');

        $first  = $tokenized->getInvocations();
        $second = $tokenized->getInvocations();

        Assert::same(\count($first), \count($second));
        Assert::same($first[0], $second[0]);
    }

    public function getInvocationsSourceContainsFullCallExpression(): void
    {
        $tokenized = self::tokenize('InvocationsFreeFunction.php');

        $invocations = $tokenized->getInvocations();

        Assert::true(\str_contains($invocations[0]->source, "SomeClass::staticCall"));
    }

    public function chainedCallOnResultIsSkippedAsNonDetectable(): void
    {
        $tokenized = self::tokenize('ChainedCallOnResult.php');

        $invocations = $tokenized->getInvocations();

        $names = \array_map(static fn($inv) => $inv->name, $invocations);
        Assert::true(\in_array('getHelper', $names, true));
        Assert::false(\in_array('doSomething', $names, true));
    }

    public function selfOutsideClassIsSkippedWhenActiveDeclarationCannotBeResolved(): void
    {
        $tokenized = self::tokenize('SelfOutsideClass.php');

        $invocations = $tokenized->getInvocations();

        Assert::same(\count($invocations), 0);
    }

    public function objectVariableMethodCallIsNotIndexed(): void
    {
        $tokenized = self::tokenize('ObjectMethodCall.php');

        $invocations = $tokenized->getInvocations();

        $names = \array_map(static fn($inv) => $inv->name, $invocations);
        Assert::false(\in_array('doWork', $names, true));
    }

    // --- namespace edge cases ---

    public function globalBracedNamespaceIsRecognized(): void
    {
        $tokenized = self::tokenize('GlobalBracedNamespace.php');

        Assert::same($tokenized->getFunctions(), ['globalBracedFunction']);
    }

    public function repeatedNamespaceBlockMergesDeclarationsFromBothBlocks(): void
    {
        $tokenized = self::tokenize('RepeatedNamespace.php');

        $classes = $tokenized->getClasses();
        \sort($classes);
        Assert::same($classes, [
            'Tests\Tokenizer\Stub\Repeated\RepeatedFirst',
            'Tests\Tokenizer\Stub\Repeated\RepeatedSecond',
        ]);
    }

    private static function tokenize(string $stub): TokenizedFile
    {
        $path = __DIR__ . '/../../Stub/' . $stub;
        return new TokenizedFile(new \SplFileInfo($path), $path);
    }
}
