<?php

declare(strict_types=1);

namespace Testo\Tokenizer\Reflection;

use Internal\Path;

/**
 * File reflections can fetch information about classes, interfaces, functions and traits declared
 * in file. In addition file reflection provides ability to fetch and describe every method/function
 * call.
 */
final class TokenizedFile
{
    /**
     * Namespace separator.
     */
    public const NS_SEPARATOR = '\\';

    /**
     * Opening and closing token ids.
     */
    public const O_TOKEN = 0;

    public const C_TOKEN = 1;

    /**
     * Namespace uses.
     */
    public const N_USES = 2;

    public readonly Path $path;

    /**
     * Indication that file contains require/include statements
     */
    public readonly bool $hasIncludes;

    /**
     * Get list of parsed tokens associated with given file.
     *
     * @var list<\PhpToken>
     */
    public readonly array $tokens;

    /**
     * Set of tokens required to detect classes, traits, interfaces and function declarations. We
     * don't need any other token for that.
     *
     * @var list<int|non-empty-string>
     */
    private static array $processTokens = [
        '{',
        '}',
        ';',
        T_PAAMAYIM_NEKUDOTAYIM,
        T_NAMESPACE,
        T_STRING,
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
        T_FUNCTION,
        T_NS_SEPARATOR,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
        T_USE,
        T_AS,
    ];

    /**
     * Total tokens count.
     *
     * @internal
     */
    private readonly int $countTokens;

    /**
     * Namespaces used in file and their token positions.
     *
     * @internal
     */
    private array $namespaces = [];

    /**
     * Declarations of classes, interfaces and traits.
     *
     * @internal
     */
    private array $declarations = [];

    /**
     * Declarations of new functions.
     *
     * @var array<non-empty-string, array{0: int<0, max>, 1: int<0, max>}>
     * @internal
     */
    private array $functions = [];

    /**
     * Token ranges of anonymous class bodies. Used to detect (and skip) their methods, which have
     * no usable FQN and must not be mistaken for free functions or outer-class methods.
     *
     * @var list<array{0: int<0, max>, 1: int<0, max>}>
     * @internal
     */
    private array $anonymous = [];

    /**
     * Declarations of new methods.
     *
     * @var array<class-string, array<non-empty-string, array{0: int<0, max>, 1: int<0, max>}>>
     * @internal
     */
    private mixed $methods = [];

    /**
     * Every found method/function invocation.
     *
     * @internal
     * @var TokenizedInvocation[]
     */
    private array $invocations = [];

    public function __construct(
        public readonly \SplFileInfo $file,
        string|Path $path,
    ) {
        $this->path = Path::create($path);
        $this->tokens = self::fetchTokens($this->path);
        $this->countTokens = \count($this->tokens);

        //Looking for declarations
        $this->locateDeclarations();
    }

    /**
     * List of declared function names
     */
    public function getFunctions(): array
    {
        return \array_keys($this->functions);
    }

    /**
     * List of declared method FQNs
     * @return list<callable-string>
     */
    public function getMethodsFQN(): array
    {
        $result = [];
        foreach ($this->methods as $class => $methods) {
            foreach ($methods as $method => $_) {
                $result[] = "$class::$method";
            }
        }

        return $result;
    }

    /**
     * List of declared class names
     */
    public function getClasses(): array
    {
        if (!isset($this->declarations['T_CLASS'])) {
            return [];
        }

        return \array_keys($this->declarations['T_CLASS']);
    }

    /**
     * List of declared enums names
     */
    public function getEnums(): array
    {
        if (!isset($this->declarations['T_ENUM'])) {
            return [];
        }

        return \array_keys($this->declarations['T_ENUM']);
    }

    /**
     * List of declared trait names
     */
    public function getTraits(): array
    {
        if (!isset($this->declarations['T_TRAIT'])) {
            return [];
        }

        return \array_keys($this->declarations['T_TRAIT']);
    }

    /**
     * List of declared interface names
     */
    public function getInterfaces(): array
    {
        if (!isset($this->declarations['T_INTERFACE'])) {
            return [];
        }

        return \array_keys($this->declarations['T_INTERFACE']);
    }

    /**
     * Locate and return list of every method or function call in specified file. Only static and
     * $this calls will be indexed
     *
     * @return TokenizedInvocation[]
     */
    public function getInvocations(): array
    {
        if (empty($this->invocations)) {
            $this->locateInvocations($this->tokens);
        }

        return $this->invocations;
    }

    /**
     * Export found declaration as array for caching purposes.
     */
    public function exportSchema(): array
    {
        return [$this->hasIncludes, $this->declarations, $this->functions, $this->namespaces];
    }

    /**
     * Import cached reflection schema.
     */
    protected function importSchema(array $cache): void
    {
        [$this->hasIncludes, $this->declarations, $this->functions, $this->namespaces] = $cache;
    }

    /**
     * Locate every class, interface, trait or function definition.
     */
    protected function locateDeclarations(): void
    {
        $hasIncludes = false;
        foreach ($this->tokens as $tokenID => $token) {
            if (!$token->is(self::$processTokens)) {
                continue;
            }

            switch ($token->id) {
                case T_NAMESPACE:
                    $this->registerNamespace($tokenID);
                    break;

                case T_USE:
                    $this->registerUse($tokenID);
                    break;

                case T_FUNCTION:
                    $this->registerFunction($tokenID);
                    break;

                case T_CLASS:
                case T_TRAIT:
                case T_INTERFACE:
                case T_ENUM:
                    if ($this->isClassNameConst($tokenID)) {
                        // PHP5.5 ClassName::class constant
                        continue 2;
                    }

                    if ($this->isAnonymousClass($tokenID)) {
                        // PHP7.0+ Anonymous class: `new class {}`, `new class (...) extends ... {}`,
                        // including an attributed one `new #[Attr] class {}`. Remember its body range
                        // so its methods are not mistaken for free functions / outer-class methods.
                        $this->anonymous[] = [
                            self::O_TOKEN => $tokenID,
                            self::C_TOKEN => $this->endingToken($tokenID),
                        ];
                        continue 2;
                    }

                    if (!$this->isCorrectDeclaration($tokenID)) {
                        // PHP8.0 Named parameters ->foo(class: 'bar')
                        continue 2;
                    }

                    $this->registerDeclaration($tokenID, $token->id);
                    break;

                case T_INCLUDE:
                case T_INCLUDE_ONCE:
                case T_REQUIRE:
                case T_REQUIRE_ONCE:
                    $hasIncludes = true;
            }
        }

        /** @psalm-suppress InaccessibleProperty */
        $this->hasIncludes = $hasIncludes;

        //Dropping empty namespace
        if (isset($this->namespaces[''])) {
            $this->namespaces['\\'] = $this->namespaces[''];
            unset($this->namespaces['']);
        }
    }

    /**
     * Get all tokens for specific file as PHP token objects.
     *
     * @return list<\PhpToken>
     */
    private static function fetchTokens(Path $filename): array
    {
        return \PhpToken::tokenize(\file_get_contents((string) $filename));
    }

    /**
     * Handle namespace declaration.
     */
    private function registerNamespace(int $tokenID): void
    {
        $namespace = '';
        $localID = $tokenID + 1;

        do {
            $token = $this->tokens[$localID++];
            if ($token->text === '{') {
                break;
            }

            $namespace .= $token->text;
        } while (
            isset($this->tokens[$localID])
            && $this->tokens[$localID]->text !== '{'
            && $this->tokens[$localID]->text !== ';'
        );

        //Whitespaces
        $namespace = \trim($namespace);

        $uses = [];
        if (isset($this->namespaces[$namespace])) {
            $uses = $this->namespaces[$namespace];
        }

        if ($this->tokens[$localID]->text === ';') {
            $endingID = \count($this->tokens) - 1;
        } else {
            $endingID = $this->endingToken($tokenID);
        }

        $this->namespaces[$namespace] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $endingID,
            self::N_USES  => $uses,
        ];
    }

    /**
     * Handle use (import class from another namespace).
     */
    private function registerUse(int $tokenID): void
    {
        $namespace = \rtrim($this->activeNamespace($tokenID), '\\');

        $class = '';
        $localAlias = null;
        for ($localID = $tokenID + 1; $this->tokens[$localID]->text !== ';'; ++$localID) {
            if ($this->tokens[$localID]->is(T_AS)) {
                $localAlias = '';
                continue;
            }

            if ($localAlias === null) {
                $class .= $this->tokens[$localID]->text;
            } else {
                $localAlias .= $this->tokens[$localID]->text;
            }
        }

        if (empty($localAlias)) {
            $names = \explode('\\', $class);
            $localAlias = \end($names);
        }

        $this->namespaces[$namespace][self::N_USES][\trim($localAlias)] = \trim($class);
    }

    /**
     * Handle function declaration (function creation).
     */
    private function registerFunction(int $tokenID): void
    {
        // Resolve the innermost class-like scope (named declaration or anonymous class) wrapping this
        // `function` token. Innermost wins, so a method of an anonymous class nested inside a named
        // class is attributed to the anonymous one — and then dropped below.
        $class = null;
        $scopeOpen = -1;
        foreach ($this->declarations as $declarations) {
            foreach ($declarations as $name => $location) {
                // Named declarations never overlap, so the first containing one is the only one.
                if ($tokenID >= $location[self::O_TOKEN] && $tokenID <= $location[self::C_TOKEN]) {
                    $scopeOpen = $location[self::O_TOKEN];
                    $class = $name;
                    break 2;
                }
            }
        }

        foreach ($this->anonymous as $range) {
            if (
                $tokenID >= $range[self::O_TOKEN]
                && $tokenID <= $range[self::C_TOKEN]
                && $range[self::O_TOKEN] > $scopeOpen
            ) {
                // Nearest enclosing scope is an anonymous class — its methods have no usable FQN.
                return;
            }
        }

        // `use function Foo\bar;` carries a T_FUNCTION token too — it imports, it does not declare.
        $prevID = $tokenID - 1;
        while ($prevID >= 0 && $this->tokens[$prevID]->is(T_WHITESPACE)) {
            --$prevID;
        }
        if ($prevID >= 0 && $this->tokens[$prevID]->is(T_USE)) {
            return;
        }

        // The name (if any) is the token right before the parameter list "(". Anonymous functions
        // have none there: `function (`, by-ref `function &(`, or an attributed closure
        // `#[Attr] function (` all reach "(" with `function`/`&` immediately before it. A parameter
        // type hint lives *inside* the parens, so it can never be misread as the name. Keyword-like
        // method names (`list`, `print`, ...) are not T_STRING, so read by position, not by type.
        $parenID = $tokenID + 1;
        while (isset($this->tokens[$parenID]) && $this->tokens[$parenID]->text !== '(') {
            ++$parenID;
        }

        if (!isset($this->tokens[$parenID])) {
            return;
        }

        $nameID = $parenID - 1;
        while ($nameID > $tokenID && $this->tokens[$nameID]->is(T_WHITESPACE)) {
            --$nameID;
        }

        $nameToken = $this->tokens[$nameID];
        if ($nameToken->is(T_FUNCTION) || $nameToken->text === '&') {
            // Anonymous function — nothing to declare.
            return;
        }

        $name = $nameToken->text;

        // Function
        if ($class === null) {
            empty($namespace = $this->activeNamespace($tokenID)) or $name = $namespace . self::NS_SEPARATOR . $name;
            $this->functions[$name] = [
                self::O_TOKEN => $tokenID,
                self::C_TOKEN => $this->endingToken($tokenID),
            ];
            return;
        }

        // Method
        $this->methods[$class][$name] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $this->endingToken($tokenID),
        ];
    }

    /**
     * Handle declaration of class, trait of interface. Declaration will be stored under it's token
     * type in declarations array.
     */
    private function registerDeclaration(int $tokenID, int $tokenType): void
    {
        $localID = $tokenID + 1;
        while (!$this->tokens[$localID]->is(T_STRING)) {
            ++$localID;
        }

        $name = $this->tokens[$localID]->text;
        if (!empty($namespace = $this->activeNamespace($tokenID))) {
            $name = $namespace . self::NS_SEPARATOR . $name;
        }

        $this->declarations[\token_name($tokenType)][$name] = [
            self::O_TOKEN => $tokenID,
            self::C_TOKEN => $this->endingToken($tokenID),
        ];
    }

    /**
     * Check if token ID represents `ClassName::class` constant statement.
     */
    private function isClassNameConst(int $tokenID): bool
    {
        return $this->tokens[$tokenID]->is(T_CLASS)
            && isset($this->tokens[$tokenID - 1])
            && $this->tokens[$tokenID - 1]->is(T_PAAMAYIM_NEKUDOTAYIM);
    }

    /**
     * Check if token ID represents anonymous class creation: `new class {}`, `new class (...) {}`,
     * `new class extends X {}`, `new class implements Y {}` — including an attributed form
     * `new #[Attr] class {}`. Detected by what follows the `class` keyword rather than by the
     * preceding `new`, so an attribute (or any token) between `new` and `class` cannot hide it.
     */
    private function isAnonymousClass(int|string $tokenID): bool
    {
        if (!$this->tokens[$tokenID]->is(T_CLASS)) {
            return false;
        }

        $nextID = $tokenID + 1;
        while (isset($this->tokens[$nextID]) && $this->tokens[$nextID]->is(T_WHITESPACE)) {
            ++$nextID;
        }

        if (!isset($this->tokens[$nextID])) {
            return false;
        }

        $next = $this->tokens[$nextID];

        return $next->text === '{'
            || $next->text === '('
            || $next->is(T_EXTENDS)
            || $next->is(T_IMPLEMENTS);
    }

    /**
     * Check if token ID represents named parameter with name `class`, e.g. `foo(class: SomeClass::name)`.
     */
    private function isCorrectDeclaration(int|string $tokenID): bool
    {
        return $this->tokens[$tokenID]->is([T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM])
            && isset($this->tokens[$tokenID + 2])
            && $this->tokens[$tokenID + 1]->is(T_WHITESPACE)
            && $this->tokens[$tokenID + 2]->is(T_STRING);
    }

    /**
     * Locate every function or static method call (including $this calls).
     *
     * This is pretty old code, potentially to be improved using AST.
     *
     * @param list<\PhpToken> $tokens
     */
    private function locateInvocations(array $tokens, int $invocationLevel = 0): void
    {
        //Multiple "(" and ")" statements nested.
        $level = 0;

        //Skip all tokens until next function
        $ignore = false;

        //Were function was found
        $invocationTID = 0;

        //Parsed arguments and their first token id
        $arguments = [];
        $argumentsTID = false;

        //Tokens used to re-enable token detection
        $stopTokens = [T_STRING, T_WHITESPACE, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NS_SEPARATOR];
        foreach ($tokens as $tokenID => $token) {
            //We are not indexing function declarations or functions called from $objects.
            if ($token->is([T_FUNCTION, T_OBJECT_OPERATOR, T_NEW])) {
                if (
                    empty($argumentsTID)
                    && (
                        empty($invocationTID)
                        || $this->getSource($invocationTID, $tokenID - 1) !== '$this'
                    )
                ) {
                    //Not a call, function declaration, or object method
                    $ignore = true;
                    continue;
                }
            } elseif ($ignore) {
                if (!$token->is($stopTokens)) {
                    //Returning to search
                    $ignore = false;
                }
                continue;
            }

            //We are inside function, and there is "(", indexing arguments.
            if (!empty($invocationTID) && ($token->text === '(' || $token->text === '[')) {
                if (empty($argumentsTID)) {
                    $argumentsTID = $tokenID;
                }

                ++$level;
                if ($level != 1) {
                    //Not arguments beginning, but arguments part
                    $arguments[$tokenID] = $token;
                }

                continue;
            }

            //We are inside function arguments and ")" met.
            if (!empty($invocationTID) && ($token->text === ')' || $token->text === ']')) {
                --$level;
                if ($level == -1) {
                    $invocationTID = false;
                    $level = 0;
                    continue;
                }

                //Function fully indexed, we can process it now.
                if ($level == 0) {
                    $this->registerInvocation(
                        $invocationTID,
                        $argumentsTID,
                        $tokenID,
                        $arguments,
                        $invocationLevel,
                    );

                    //Closing search
                    $arguments = [];
                    $argumentsTID = $invocationTID = false;
                } else {
                    //Not arguments beginning, but arguments part
                    $arguments[$tokenID] = $token;
                }

                continue;
            }

            //Still inside arguments.
            if (!empty($invocationTID) && !empty($level)) {
                $arguments[$tokenID] = $token;
                continue;
            }

            //Nothing valuable to remember, will be parsed later.
            if (!empty($invocationTID) && $token->is($stopTokens)) {
                continue;
            }

            //Seems like we found function/method call
            if (
                $token->is(T_STRING)
                || $token->is(T_STATIC)
                || $token->is(T_NS_SEPARATOR)
                || ($token->is(T_VARIABLE) && $token->text === '$this')
            ) {
                $invocationTID = $tokenID;
                $level = 0;

                $argumentsTID = false;
                continue;
            }

            //Returning to search
            $invocationTID = false;
            $arguments = [];
        }
    }

    /**
     * Registering invocation.
     */
    private function registerInvocation(
        int $invocationID,
        int $argumentsID,
        int $endID,
        array $arguments,
        int $invocationLevel,
    ): void {
        //Nested invocations
        $this->locateInvocations($arguments, $invocationLevel + 1);

        [$class, $operator, $name] = $this->fetchContext($invocationID, $argumentsID);

        if (!empty($operator) && empty($class)) {
            //Non detectable
            return;
        }

        $this->invocations[] = new TokenizedInvocation(
            $this->path,
            $this->lineNumber($invocationID),
            $class,
            $operator,
            $name,
            TokenizedArgument::locateArguments($arguments),
            $this->getSource($invocationID, $endID),
            $invocationLevel,
        );
    }

    /**
     * Fetching invocation context.
     * @return array{class-string|"", "::"|"->"|"", non-empty-string} [class, operator, name]
     */
    private function fetchContext(int $invocationTID, int $argumentsTID): array
    {
        $class = $operator = '';
        $name = \trim($this->getSource($invocationTID, $argumentsTID), '( ');

        //Let's try to fetch all information we need
        if (\str_contains($name, '->')) {
            $operator = '->';
        } elseif (\str_contains($name, '::')) {
            $operator = '::';
        }

        if (!empty($operator)) {
            [$class, $name] = \explode($operator, $name);

            //We now have to clarify class name
            if (\in_array($class, ['self', 'static', '$this'])) {
                $class = $this->activeDeclaration($invocationTID);
            }
        }

        return [$class, $operator, $name];
    }

    /**
     * Get declaration which is active in given token position.
     */
    private function activeDeclaration(int $tokenID): string
    {
        foreach ($this->declarations as $declarations) {
            foreach ($declarations as $name => $position) {
                if ($tokenID >= $position[self::O_TOKEN] && $tokenID <= $position[self::C_TOKEN]) {
                    return $name;
                }
            }
        }

        //Can not be detected
        return '';
    }

    /**
     * Get namespace name active at specified token position.
     */
    private function activeNamespace(int $tokenID): string
    {
        foreach ($this->namespaces as $namespace => $position) {
            if ($tokenID >= $position[self::O_TOKEN] && $tokenID <= $position[self::C_TOKEN]) {
                return $namespace;
            }
        }

        //Seems like no namespace declaration
        $this->namespaces[''] = [
            self::O_TOKEN => 0,
            self::C_TOKEN => \count($this->tokens),
            self::N_USES  => [],
        ];

        return '';
    }

    /**
     * Find token ID of ending brace.
     */
    private function endingToken(int $tokenID): int
    {
        $level = 0;
        $entered = false;
        for ($localID = $tokenID; $localID < $this->countTokens; ++$localID) {
            $token = $this->tokens[$localID];
            if ($token->text === '{') {
                ++$level;
                $entered = true;
                continue;
            }

            if ($token->text === '}') {
                --$level;
            }

            if ($entered && $level === 0) {
                break;
            }
        }

        return $localID;
    }

    /**
     * Get line number associated with token.
     */
    private function lineNumber(int $tokenID): int
    {
        return $this->tokens[$tokenID]->line;
    }

    /**
     * Get src located between two tokens.
     */
    private function getSource(int $startID, int $endID): string
    {
        $result = '';
        for ($tokenID = $startID; $tokenID <= $endID; ++$tokenID) {
            //Collecting function usage src
            $result .= $this->tokens[$tokenID]->text;
        }

        return $result;
    }
}
