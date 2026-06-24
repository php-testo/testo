<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit\Reflection;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;
use Testo\Tokenizer\Exception\ReflectionException;
use Testo\Tokenizer\Reflection\TokenizedArgument;

#[Test]
#[Covers(TokenizedArgument::class)]
final class TokenizedArgumentTest
{
    // --- constructor, getType, getValue ---

    public function constructorStoresTypeAndValue(): void
    {
        $arg = new TokenizedArgument(TokenizedArgument::EXPRESSION, '$foo + 1');

        Assert::same(TokenizedArgument::EXPRESSION, $arg->getType());
        Assert::same('$foo + 1', $arg->getValue());
    }

    #[DataSet([TokenizedArgument::CONSTANT,   'constant'],   'constant type')]
    #[DataSet([TokenizedArgument::VARIABLE,   'variable'],   'variable type')]
    #[DataSet([TokenizedArgument::EXPRESSION, 'expression'], 'expression type')]
    #[DataSet([TokenizedArgument::STRING,     'string'],     'string type')]
    public function getTypeReturnsStoredType(string $type, string $expectedConst): void
    {
        $arg = new TokenizedArgument($type, 'x');

        Assert::same($expectedConst, $arg->getType());
    }

    // --- locateArguments ---

    public function locateArgumentsReturnsEmptyForEmptyTokenList(): void
    {
        $result = TokenizedArgument::locateArguments([]);

        Assert::same([], $result);
    }

    public function locateArgumentsSkipsWhitespaceOnlyTokens(): void
    {
        $tokens = self::tokenize('<?php foo( );');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::same([], $result);
    }

    public function locateArgumentsDetectsVariableArgument(): void
    {
        $tokens = self::tokenize('<?php foo($myVar);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::VARIABLE, $result[0]->getType());
        Assert::same('$myVar', $result[0]->getValue());
    }

    public function locateArgumentsDetectsIntegerConstantArgument(): void
    {
        $tokens = self::tokenize('<?php foo(42);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::CONSTANT, $result[0]->getType());
        Assert::same('42', $result[0]->getValue());
    }

    public function locateArgumentsDetectsFloatConstantArgument(): void
    {
        $tokens = self::tokenize('<?php foo(3.14);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::CONSTANT, $result[0]->getType());
        Assert::same('3.14', $result[0]->getValue());
    }

    public function locateArgumentsDetectsStringArgument(): void
    {
        $tokens = self::tokenize("<?php foo('hello');");
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::STRING, $result[0]->getType());
        Assert::same("'hello'", $result[0]->getValue());
    }

    public function locateArgumentsDetectsMultipleArguments(): void
    {
        $tokens = self::tokenize('<?php foo($a, 1, \'b\');');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 3);
        Assert::same(TokenizedArgument::VARIABLE, $result[0]->getType());
        Assert::same(TokenizedArgument::CONSTANT, $result[1]->getType());
        Assert::same(TokenizedArgument::STRING,   $result[2]->getType());
    }

    public function locateArgumentsDetectsExpressionForMultiTokenArgument(): void
    {
        $tokens = self::tokenize('<?php foo($a + 1);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::EXPRESSION, $result[0]->getType());
        Assert::same('$a+1', $result[0]->getValue());
    }

    public function locateArgumentsTracksNestingLevelForParentheses(): void
    {
        // The nested call foo(bar($x, $y)) — the inner comma is inside level 1 and must NOT split.
        $tokens = self::tokenize('<?php foo(bar($x, $y));');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::EXPRESSION, $result[0]->getType());
    }

    public function locateArgumentsTracksNestingLevelForBrackets(): void
    {
        // Array bracket: foo([$a, $b]) — inner comma must NOT split arguments.
        $tokens = self::tokenize('<?php foo([$a, $b]);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        Assert::count($result, 1);
        Assert::same(TokenizedArgument::EXPRESSION, $result[0]->getType());
    }

    public function locateArgumentsIgnoresLastArgumentWhenEmpty(): void
    {
        // A token stream ending with a comma would leave an empty definition; it should be dropped.
        $tokens = self::tokenize('<?php foo($x,);');
        $argTokens = self::extractArgumentTokens($tokens);

        $result = TokenizedArgument::locateArguments($argTokens);

        // Only $x is a real argument; the trailing comma leaves an empty definition that is discarded.
        Assert::count($result, 1);
        Assert::same('$x', $result[0]->getValue());
    }

    // --- stringValue ---

    public function stringValueReturnsEvaledStringForStringType(): void
    {
        $arg = new TokenizedArgument(TokenizedArgument::STRING, "'hello world'");

        $result = $arg->stringValue();

        Assert::same('hello world', $result);
    }

    public function stringValueReturnsEvaledDoubleQuotedString(): void
    {
        $arg = new TokenizedArgument(TokenizedArgument::STRING, '"foo bar"');

        $result = $arg->stringValue();

        Assert::same('foo bar', $result);
    }

    #[DataSet([TokenizedArgument::CONSTANT],   'constant type')]
    #[DataSet([TokenizedArgument::VARIABLE],   'variable type')]
    #[DataSet([TokenizedArgument::EXPRESSION], 'expression type')]
    public function stringValueThrowsForNonStringType(string $type): void
    {
        $arg = new TokenizedArgument($type, 'x');

        Expect::exception(ReflectionException::class)
            ->withMessage("Unable to represent value as string, value type is '$type'");

        $arg->stringValue();
    }

    // --- helpers ---

    /**
     * Tokenize a PHP snippet and return all \PhpToken objects.
     *
     * @return \PhpToken[]
     */
    private static function tokenize(string $code): array
    {
        return \PhpToken::tokenize($code);
    }

    /**
     * Extract the tokens that appear between the opening '(' and closing ')' of the first
     * function call in the token stream, excluding those parentheses themselves.
     *
     * @param \PhpToken[] $tokens
     * @return \PhpToken[]
     */
    private static function extractArgumentTokens(array $tokens): array
    {
        $start = null;
        foreach ($tokens as $i => $token) {
            if ($token->text === '(') {
                $start = $i;
                break;
            }
        }

        if ($start === null) {
            return [];
        }

        $result = [];
        $level = 0;
        for ($i = $start; $i < \count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token->text === '(') {
                if ($level === 0) {
                    $level = 1;
                    continue; // skip the outer opening paren
                }
                $level++;
            } elseif ($token->text === ')') {
                $level--;
                if ($level === 0) {
                    break; // stop at the outer closing paren
                }
            }
            $result[] = $token;
        }

        return $result;
    }
}
