<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit;

use Testo\Application\Internal\MessengerHub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\ErrorReporter;
use Testo\Core\Log\Level;
use Testo\Test;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Common\Stub\SpyDispatcher;

#[Test]
#[Covers(DefinitionLocator::class)]
final class DefinitionLocatorTest
{
    // --- getFunctions ---

    public function getFunctionsReturnsReflectionFunctionForEachDeclaredFunction(): void
    {
        $file = self::tokenize('OnlyFunctions.php');

        $result = DefinitionLocator::getFunctions($file);

        Assert::same(\array_keys($result), [
            'Tests\Tokenizer\Stub\freeFunctionOne',
            'Tests\Tokenizer\Stub\freeFunctionTwo',
        ]);
        Assert::true($result['Tests\Tokenizer\Stub\freeFunctionOne'] instanceof \ReflectionFunction);
        Assert::true($result['Tests\Tokenizer\Stub\freeFunctionTwo'] instanceof \ReflectionFunction);
    }

    public function getFunctionsReturnsEmptyArrayWhenFileHasNoFunctions(): void
    {
        $file = self::tokenize('EmptyClass.php');

        $result = DefinitionLocator::getFunctions($file);

        Assert::same($result, []);
    }

    public function getFunctionsAcceptsNullReporterWithoutError(): void
    {
        $file = self::tokenize('OnlyFunctions.php');

        $result = DefinitionLocator::getFunctions($file, null);

        Assert::count($result, 2);
    }

    public function getFunctionsSkipsAndReportsWhenFunctionNeverDefined(): void
    {
        // The tokenizer sees the function declaration but PHP never defines it
        // (it is inside `if (false) {}`), so loadReflection throws LocatorException.
        $file = self::tokenize('ConditionallyDefinedFunction.php');
        $spy = new SpyDispatcher();
        $reporter = new ErrorReporter(new MessengerHub($spy));

        $result = DefinitionLocator::getFunctions($file, $reporter);

        Assert::same($result, []);

        $messages = $spy->messages();
        Assert::count($messages, 1);
        Assert::same($messages[0]->message->level, Level::Debug);
    }

    public function getFunctionsSkipsWithoutReporterWhenFunctionNeverDefined(): void
    {
        $file = self::tokenize('ConditionallyDefinedFunction.php');

        $result = DefinitionLocator::getFunctions($file);

        Assert::same($result, []);
    }

    // --- getInterfaces ---

    public function getInterfacesReturnsReflectionClassForEachDeclaredInterface(): void
    {
        $file = self::tokenize('TraitAndInterface.php');

        $result = DefinitionLocator::getInterfaces($file);

        Assert::same(\array_keys($result), ['Tests\Tokenizer\Stub\MyInterface']);
        Assert::true($result['Tests\Tokenizer\Stub\MyInterface'] instanceof \ReflectionClass);
        Assert::true($result['Tests\Tokenizer\Stub\MyInterface']->isInterface());
    }

    public function getInterfacesReturnsEmptyArrayWhenFileHasNoInterfaces(): void
    {
        $file = self::tokenize('EmptyClass.php');

        $result = DefinitionLocator::getInterfaces($file);

        Assert::same($result, []);
    }

    public function getInterfacesSkipsAndReportsWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalInterfaceWithUnloadableDependency.php');
        $spy = new SpyDispatcher();
        $reporter = new ErrorReporter(new MessengerHub($spy));

        $result = DefinitionLocator::getInterfaces($file, $reporter);

        Assert::same($result, []);

        $messages = $spy->messages();
        Assert::count($messages, 1);
        Assert::same($messages[0]->message->level, Level::Debug);
    }

    public function getInterfacesSkipsWithoutReporterWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalInterfaceWithUnloadableDependency.php');

        $result = DefinitionLocator::getInterfaces($file);

        Assert::same($result, []);
    }

    // --- getClasses ---

    public function getClassesReturnsReflectionClassForEachDeclaredClass(): void
    {
        $file = self::tokenize('EmptyClass.php');

        $result = DefinitionLocator::getClasses($file);

        Assert::same(\array_keys($result), ['Tests\Tokenizer\Stub\EmptyClass']);
        Assert::true($result['Tests\Tokenizer\Stub\EmptyClass'] instanceof \ReflectionClass);
    }

    public function getClassesReturnsEmptyArrayWhenFileHasNoClasses(): void
    {
        $file = self::tokenize('OnlyFunctions.php');

        $result = DefinitionLocator::getClasses($file);

        Assert::same($result, []);
    }

    public function getClassesExcludesInterfacesAndTraitsFromResult(): void
    {
        $file = self::tokenize('TraitAndInterface.php');

        $result = DefinitionLocator::getClasses($file);

        Assert::same($result, []);
    }

    public function getClassesSkipsAndReportsWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalClassWithUnloadableDependency.php');
        $spy = new SpyDispatcher();
        $reporter = new ErrorReporter(new MessengerHub($spy));

        $result = DefinitionLocator::getClasses($file, $reporter);

        Assert::same($result, []);

        $messages = $spy->messages();
        Assert::count($messages, 1);
        Assert::same($messages[0]->message->level, Level::Debug);
    }

    public function getClassesSkipsWithoutReporterWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalClassWithUnloadableDependency.php');

        $result = DefinitionLocator::getClasses($file);

        Assert::same($result, []);
    }

    public function getClassesHandlesMultipleClassesInOneFile(): void
    {
        $file = self::tokenize('TwoClassesInOneFile.php');

        $result = DefinitionLocator::getClasses($file);

        $names = \array_keys($result);
        \sort($names);
        Assert::same($names, [
            'Tests\Tokenizer\Stub\FirstClass',
            'Tests\Tokenizer\Stub\SecondClass',
        ]);
    }

    // --- getEnums ---

    public function getEnumsReturnsReflectionEnumForEachDeclaredEnum(): void
    {
        $file = self::tokenize('EnumDeclaration.php');

        $result = DefinitionLocator::getEnums($file);

        $names = \array_keys($result);
        \sort($names);
        Assert::same($names, [
            'Tests\Tokenizer\Stub\Color',
            'Tests\Tokenizer\Stub\Status',
        ]);
        Assert::true($result['Tests\Tokenizer\Stub\Color'] instanceof \ReflectionEnum);
        Assert::true($result['Tests\Tokenizer\Stub\Status'] instanceof \ReflectionEnum);
    }

    public function getEnumsReturnsEmptyArrayWhenFileHasNoEnums(): void
    {
        $file = self::tokenize('EmptyClass.php');

        $result = DefinitionLocator::getEnums($file);

        Assert::same($result, []);
    }

    public function getEnumsSkipsAndReportsWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalEnumWithUnloadableDependency.php');
        $spy = new SpyDispatcher();
        $reporter = new ErrorReporter(new MessengerHub($spy));

        $result = DefinitionLocator::getEnums($file, $reporter);

        Assert::same($result, []);

        $messages = $spy->messages();
        Assert::count($messages, 1);
        Assert::same($messages[0]->message->level, Level::Debug);
    }

    public function getEnumsSkipsWithoutReporterWhenDependencyCannotBeLoaded(): void
    {
        $file = self::tokenize('GlobalEnumWithUnloadableDependency.php');

        $result = DefinitionLocator::getEnums($file);

        Assert::same($result, []);
    }

    private static function tokenize(string $stub): TokenizedFile
    {
        $path = \dirname(__DIR__) . '/Stub/' . $stub;
        return new TokenizedFile(new \SplFileInfo($path), $path);
    }
}
