<?php

declare(strict_types=1);

namespace Testo\Tokenizer\Reflection;

use Testo\Tokenizer\Exception\ReflectionException;

/**
 * Represent argument using in method or function invocation with it's type and value.
 */
final class TokenizedArgument
{
    /**
     * Argument types.
     */
    public const CONSTANT   = 'constant';   //Scalar constant and not variable.

    public const VARIABLE   = 'variable';   //PHP variable
    public const EXPRESSION = 'expression'; //PHP code (expression).
    public const STRING     = 'string';     //Simple scalar string, can be fetched using stringValue().

    /**
     * New instance of ReflectionArgument.
     *
     * @param string $type  Argument type (see top constants).
     * @param string $value Value in a form of php code.
     */
    public function __construct(
        private string $type,
        private readonly string $value,
    ) {}

    /**
     * Create Argument reflections based on provided set of tokens (fetched from invoke).
     *
     * @return self[]
     */
    public static function locateArguments(array $tokens): array
    {
        $definition = null;
        $level = 0;

        $result = [];
        foreach ($tokens as $token) {
            if ($token->is(T_WHITESPACE)) {
                continue;
            }

            if (empty($definition)) {
                $definition = ['type' => self::EXPRESSION, 'value' => '', 'tokens' => []];
            }

            if ($token->text === '(' || $token->text === '[') {
                ++$level;
                $definition['value'] .= $token->text;
                continue;
            }

            if ($token->text === ')' || $token->text === ']') {
                --$level;
                $definition['value'] .= $token->text;
                continue;
            }

            if ($level) {
                $definition['value'] .= $token->text;
                continue;
            }

            if ($token->text === ',') {
                $result[] = self::createArgument($definition);
                $definition = null;
                continue;
            }

            $definition['tokens'][] = $token;
            $definition['value'] .= $token->text;
        }

        //Last argument
        if (\is_array($definition)) {
            $definition = self::createArgument($definition);
            if (!empty($definition->getType())) {
                $result[] = $definition;
            }
        }

        return $result;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Convert argument value into valid string. Can be applied only for STRING type arguments.
     *
     * @throws ReflectionException When value can not be converted into string.
     */
    public function stringValue(): string
    {
        if ($this->type !== self::STRING) {
            throw new ReflectionException(
                \sprintf("Unable to represent value as string, value type is '%s'", $this->type),
            );
        }

        //The most reliable way
        return eval("return {$this->value};");
    }

    /**
     * Create Argument reflection using token definition. Internal method.
     *
     * @param array{value: string, tokens: array, type: string} $definition
     * @see locateArguments
     */
    private static function createArgument(array $definition): TokenizedArgument
    {
        $result = new self(self::EXPRESSION, $definition['value']);

        if (\count($definition['tokens']) == 1) {
            $result->type = match ($definition['tokens'][0]->id) {
                T_VARIABLE => self::VARIABLE,
                T_LNUMBER, T_DNUMBER => self::CONSTANT,
                T_CONSTANT_ENCAPSED_STRING => self::STRING,
                default => $result->type,
            };
        }

        return $result;
    }
}
