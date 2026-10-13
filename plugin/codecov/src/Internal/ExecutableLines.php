<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

/**
 * Estimates the executable lines of PHP source from its tokens, without loading it.
 *
 * Stands in for a coverage driver on files no test loaded, so they reach the report with every line
 * unexecuted. The estimate follows the lines Xdebug and PCOV report, which are the lines PHP attaches
 * opcodes to: code inside function bodies, a reachable closing brace that carries an implicit `return`,
 * the signature of a generator or of a constructor that promotes properties, inline HTML, and top-level
 * statements other than declarations. Drivers and PHP versions disagree on a few constructs, so a
 * file's count can be a few lines off.
 *
 * @internal
 */
final class ExecutableLines
{
    private const CLASS_LIKE = [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM];

    /** Punctuation that never makes a line executable by itself. */
    private const NEUTRAL = ['{', '}', '(', ')', '[', ']', ',', ';', ':'];

    /** Keywords that only join blocks, and heredoc/nowdoc content. */
    private const INERT = [
        \T_ELSE, \T_TRY, \T_FINALLY, \T_DO,
        \T_ENDIF, \T_ENDFOREACH, \T_ENDFOR, \T_ENDWHILE, \T_ENDSWITCH, \T_ENDDECLARE,
        \T_START_HEREDOC, \T_END_HEREDOC, \T_ENCAPSED_AND_WHITESPACE,
    ];

    /** Tokens that keep an array literal from being folded into a constant. */
    private const RUNTIME = [
        \T_VARIABLE, \T_NEW, \T_FN, \T_FUNCTION, \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR,
        \T_ELLIPSIS, \T_INC, \T_DEC, \T_STATIC,
    ];

    /** Tokens of a `match` subject that leave an opcode on its line. */
    private const SUBJECT_OPCODE = [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON];

    /** Literals a `match` can dispatch on through a jump table. */
    private const JUMP_KEY = [\T_LNUMBER, \T_CONSTANT_ENCAPSED_STRING];

    /** Heads of top-level statements that compile to no opcode. */
    private const DECLARATION = [
        \T_NAMESPACE, \T_USE, \T_DECLARE, \T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM,
        \T_ABSTRACT, \T_FINAL, \T_READONLY, \T_FUNCTION, \T_ATTRIBUTE,
    ];

    /** Keywords that continue a compound statement rather than starting a new one. */
    private const CONTINUATION = [\T_ELSE, \T_ELSEIF, \T_CATCH, \T_FINALLY];

    /** Tokens after which a name followed by `:` is a `goto` label. */
    private const STATEMENT_START = [';', '{', '}', ':'];

    /** Modifiers that promote a parameter, compared by text: `private(set)` has a token of its own on PHP 8.4. */
    private const PROMOTION = ['public', 'protected', 'private', 'readonly', 'public(set)', 'protected(set)', 'private(set)'];

    /** Tokens after which a declaration keyword is a name: of a method, a class member or a property. */
    private const NAME_PREFIX = [\T_FUNCTION, \T_DOUBLE_COLON, \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR];

    /** Tokens after which `(` opens the argument list of a call. */
    private const CALLEE = [
        \T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE, \T_VARIABLE, \T_STATIC,
        ')', ']', '}',
    ];

    /**
     * @return array<int<1, max>, true> Line number => true.
     */
    public static function of(string $source): array
    {
        $lines = [];

        # Contexts opened by `{`: 'func' (a function body), 'constant' (the body of a closure in a constant
        # expression), 'class', 'block', 'match' (a `match` body), 'interp' (`{$` in a string).
        $stack = [];
        # Open `(` and `[` of the current brace context, saved and restored around nested ones. An array
        # entry collects the lines of its constant elements until it closes and shows whether it folds.
        /** @var list<array{type: '('|'[', line: int, const: bool, weak: array<int, true>, call: bool, index: int, shape: list<int>, held: array<int, true>, resolved: bool}> $brackets */
        $brackets = [];
        $savedBrackets = [];

        # What the next `{` opens, once a declaration keyword is seen, and for a function whether it is
        # an anonymous one in a constant expression.
        $pending = null;
        $signatureLine = 0;
        $promotes = false;
        $parens = 0;
        $constant = false;
        $anonymous = null;
        $resumes = false;
        # A promoted parameter with hooks leaves the constructor signature without an opcode.
        $hooked = false;
        # Signatures suspended by a closure in a default value or by hooks of a promoted parameter, and per
        # `constant` or `hooks` depth whether one resumes.
        $suspended = [];
        $resume = [];
        $previous = null;
        $beforePrevious = null;
        # A `switch` or `match` subject being read: its bracket depth, kind and line.
        $header = null;
        # Line of a `return` or `yield` whose opcode lands on the line of its value, and the array taking it.
        $returnLine = null;
        $returnArray = false;
        # Open arrow functions: the brace depth and bracket count their body ends at.
        $arrows = [];
        # Line of the first text part of the heredoc being read: null before it, false once settled.
        $heredocText = false;
        # Whether the class member being read declares a property, whose `{` opens its hooks.
        $property = false;
        # The class before the last `::`, and the name of the class being declared.
        $scope = null;
        $className = null;
        # Depths of the hooks written as `get => expr;`, a body without braces.
        $shortHooks = [];

        # Per statement depth: the first token of the current statement, whether the last finished one
        # leaves the block, and the `if` or `try` it continues.
        $head = [];
        $exits = [];
        $compound = [];
        # Per function body depth: the signature line and whether the body yields.
        $signature = [];
        $generator = [];
        # Per `match` body depth: what its arms dispatch on.
        $arms = [];
        $topHead = null;

        $tokens = \PhpToken::tokenize($source);
        foreach ($tokens as $i => $t) {
            if ($t->isIgnorable() || $t->is([\T_OPEN_TAG, \T_CLOSE_TAG])) {
                continue;
            }

            $depth = \count($stack);
            $funcDepth = self::funcDepth($stack);
            $atTop = $funcDepth === null && !\in_array('class', $stack, true) && !\in_array('constant', $stack, true);
            $inBody = $funcDepth !== null;
            $significant = !\in_array($t->text, self::NEUTRAL, true);
            $atTop && $significant and $topHead ??= $t;
            # Only code runs: a function body, or a top-level statement that is not a declaration.
            $inCode = $inBody || ($atTop && !$topHead?->is(self::DECLARATION));
            # Statement depth: a `match` body or a string interpolation belongs to the enclosing statement.
            $level = $depth;
            while ($level > 0 && \in_array($stack[$level - 1], ['match', 'interp'], true)) {
                $level--;
            }

            if ($t->id === \T_INLINE_HTML) {
                # Echoed when reached, so the line it starts on is executable.
                ($inBody || $atTop) && \trim($t->text) !== '' and $lines[$t->line] = true;
                # A statement of its own: what follows starts the next one.
                $atTop and $topHead = null;
                continue;
            }

            if ($t->id === \T_HALT_COMPILER) {
                # Everything after it is raw data.
                $lines[$t->line] = true;
                break;
            }

            # A heredoc that interpolates is assembled at runtime, starting on the line of its first text part.
            if ($t->id === \T_START_HEREDOC) {
                $heredocText = null;
            } elseif ($t->id === \T_END_HEREDOC) {
                $heredocText = false;
            } elseif ($t->id === \T_ENCAPSED_AND_WHITESPACE) {
                $heredocText ??= $t->line;
            } elseif (\is_int($heredocText)) {
                $inCode and $lines[$heredocText] = true;
                $heredocText = false;
            }

            # A `return` or `yield` takes the line of its value, except that an array literal or a `match`
            # puts it on the line of the first element or arm.
            if ($returnLine !== null) {
                if ($t->text === '[' || $t->is([\T_ARRAY, \T_MATCH])) {
                    $returnArray = $t->id !== \T_MATCH;
                } else {
                    $lines[$returnLine] = true;
                }
                $returnLine = null;
            }

            # A declaration keyword also names a method, a class member or a named argument (`f(class: $x)`).
            $isName = $t->is([...self::CLASS_LIKE, \T_FUNCTION]) && (
                $previous?->is(self::NAME_PREFIX)
                || ($previous?->text === '&' && $beforePrevious?->id === \T_FUNCTION)
                || self::next($tokens, $i)?->text === ':'
            );

            $t->id === \T_DOUBLE_COLON and $scope = $previous;
            $t->id === \T_STRING && $previous?->is(self::CLASS_LIKE) and $className = $t->text;

            # The lexer emits `T_ENUM` only before a name, so a soft-keyword `enum` never lands here.
            if ($isName) {
                $anonymous === null && $pending === 'func' and $anonymous = false;
            } elseif ($t->is(self::CLASS_LIKE)) {
                $pending = 'class';
            } elseif ($t->id === \T_FUNCTION) {
                # A closure as a default value suspends the signature it sits in until it closes.
                $resumes = $pending === 'func';
                $resumes and $suspended[] = [$signatureLine, $promotes, $parens, $constant, $anonymous, $hooked];
                $hooked = false;
                # A closure outside running code belongs to a constant expression: an attribute, a constant
                # or a default value. Drivers never report its body.
                $constant = !$inCode || $resumes;
                $anonymous = null;
                $pending = 'func';
                $signatureLine = $t->line;
                $promotes = false;
                $parens = 0;
            } elseif ($anonymous === null && $pending === 'func' && $t->text !== '&') {
                # A method may be named by a keyword (`print`, `array`), so only `(` tells a closure.
                $anonymous = $t->text === '(';
            }

            # Parameters, `use` and the return type compile to no opcode on their own lines.
            $inSignature = $pending === 'func' && $t->id !== \T_FUNCTION;
            if ($pending === 'func') {
                $t->text === '(' and $parens++;
                $t->text === ')' and $parens--;
                $parens > 0 && \in_array(\strtolower($t->text), self::PROMOTION, true) and $promotes = true;
                # A body-less method: abstract or declared by an interface.
                $t->text === ';' && $parens === 0 and $pending = null;
            }

            # `T_CURLY_OPEN` reads `{` too, so interpolation is told apart by id.
            $interpolation = $t->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES]);
            if ($t->text === '{' || $interpolation) {
                $brackets !== [] && !$interpolation and self::markRuntime($brackets);
                if ($interpolation) {
                    $stack[] = 'interp';
                } elseif ($pending === 'func' && $parens > 0) {
                    # Hooks of a promoted parameter: the signature resumes after them.
                    $stack[] = 'hooks';
                    $suspended[] = [$signatureLine, $promotes, $parens, $constant, $anonymous, true];
                    $resume[$depth + 1] = true;
                    $pending = null;
                } elseif ($property && $pending === null && ($stack[$depth - 1] ?? null) === 'class') {
                    $stack[] = 'hooks';
                    $resume[$depth + 1] = false;
                } elseif ($pending === null && ($stack[$depth - 1] ?? null) === 'hooks') {
                    # The body of a property hook.
                    $stack[] = 'func';
                    $signature[$depth + 1] = $t->line;
                    $generator[$depth + 1] = false;
                } elseif ($pending === 'func' && $parens === 0 && $constant && $anonymous) {
                    $stack[] = 'constant';
                    $resume[$depth + 1] = $resumes;
                    $pending = null;
                } elseif ($pending !== null && $parens === 0) {
                    $stack[] = $pending;
                    if ($pending === 'func') {
                        $promotes && !$hooked and $lines[$signatureLine] = true;
                        $signature[$depth + 1] = $signatureLine;
                        $generator[$depth + 1] = false;
                    }
                    $pending = null;
                } elseif ($header !== null && $header['kind'] === 'match' && $header['at'] === \count($brackets)) {
                    $stack[] = 'match';
                    $arms[$depth + 1] = ['line' => $header['line'], 'keys' => 0, 'type' => null, 'table' => true, 'cond' => [], 'inCond' => true];
                } else {
                    # A block at the top level, such as `namespace X { … }`, holds statements of its own.
                    $atTop and $topHead = null;
                    $stack[] = 'block';
                }
                $header !== null && $header['at'] === \count($brackets) and $header = null;
                $head[$depth + 1] = null;
                $exits[$depth + 1] = false;
                $compound[$depth + 1] = null;
                $property = false;
                $savedBrackets[] = $brackets;
                $brackets = [];
                $beforePrevious = $previous;
                $previous = $t;
                continue;
            }

            if ($t->text === '}') {
                $closing = \array_pop($stack);
                $brackets = \array_pop($savedBrackets) ?? [];
                $leaves = self::statementExits($exits[$depth] ?? false, $compound[$depth] ?? null);
                unset($head[$depth], $exits[$depth], $compound[$depth]);

                $property = false;

                if ($closing === 'constant' || $closing === 'hooks') {
                    if ($resume[$depth]) {
                        [$signatureLine, $promotes, $parens, $constant, $anonymous, $hooked] = \array_pop($suspended);
                        $pending = 'func';
                    }
                    unset($resume[$depth]);
                } elseif ($closing === 'func') {
                    # Falling off the end of a body runs an implicit `return` on its closing brace.
                    $leaves or $lines[$t->line] = true;
                    # A generator is created when the call starts, on the signature.
                    $generator[$depth] and $lines[$signature[$depth]] = true;
                    # A closure passed to a call is sent from the line it ends on.
                    $brackets !== [] && \end($brackets)['call'] and $lines[$t->line] = true;
                    unset($signature[$depth], $generator[$depth]);
                } elseif ($closing === 'match') {
                    $a = self::endArmCondition($arms[$depth]);
                    # Literal keys of one type let PHP dispatch through a table, an opcode on the subject line.
                    $a['table'] && $a['keys'] >= 2 and $lines[$a['line']] = true;
                    unset($arms[$depth]);
                } elseif ($closing === 'block') {
                    $parent = $depth - 1;
                    $opener = $head[$parent] ?? null;
                    if (($compound[$parent] ?? null) !== null && $opener?->is([\T_IF, \T_ELSEIF, \T_ELSE, \T_TRY, \T_CATCH, \T_FINALLY])) {
                        $opener->id === \T_FINALLY
                            ? $compound[$parent]['finally'] = $leaves
                            : $compound[$parent]['exits'] = $compound[$parent]['exits'] && $leaves;
                    } else {
                        $compound[$parent] = null;
                        $exits[$parent] = false;
                    }
                    $head[$parent] = null;
                }
                # Back in top-level code, the next token starts a statement.
                $closing !== 'interp' && $closing !== 'match' && self::funcDepth($stack) === null
                    && !\in_array('class', $stack, true) && !\in_array('constant', $stack, true)
                    and $topHead = null;
                $beforePrevious = $previous;
                $previous = $t;
                continue;
            }

            # A short property hook, `get => expr;`, is a body without braces.
            $short = \end($shortHooks);
            if ($t->id === \T_DOUBLE_ARROW && $brackets === [] && ($stack[$depth - 1] ?? null) === 'hooks') {
                $stack[] = 'func';
                $shortHooks[] = $depth + 1;
                $signature[$depth + 1] = $t->line;
                $generator[$depth + 1] = false;
                $head[$depth + 1] = null;
                $exits[$depth + 1] = false;
                $compound[$depth + 1] = null;
                $savedBrackets[] = $brackets;
                $beforePrevious = $previous;
                $previous = $t;
                continue;
            } elseif ($short === $depth && $t->text === ';' && $brackets === []) {
                \array_pop($stack);
                \array_pop($shortHooks);
                $brackets = \array_pop($savedBrackets) ?? [];
                unset($head[$depth], $exits[$depth], $compound[$depth], $signature[$depth], $generator[$depth]);
                while (\end($arrows) !== false && \end($arrows)[0] >= $depth) {
                    \array_pop($arrows);
                }
                $beforePrevious = $previous;
                $previous = $t;
                continue;
            }

            if ($t->text === '(' || $t->text === '[') {
                $isArray = $t->text === '[' || $previous?->id === \T_ARRAY;
                # A call keeps the enclosing array from folding.
                !$isArray && $brackets !== [] && !$previous?->is(self::NEUTRAL) and self::markRuntime($brackets);
                $top = \array_key_last($brackets);
                $top !== null && $brackets[$top]['call'] and $brackets[$top]['shape'][] = $t->id;
                $brackets[] = [
                    'type' => $isArray ? '[' : '(',
                    'line' => $t->line,
                    'const' => true,
                    'weak' => [],
                    'call' => !$isArray && !$inSignature && $previous?->is(self::CALLEE),
                    'index' => 0,
                    'shape' => [],
                    'held' => [],
                    'resolved' => ($previous?->id === \T_NAME_FULLY_QUALIFIED && self::builtInFunction($previous->text))
                        || ($previous?->id === \T_STRING && $beforePrevious?->id === \T_DOUBLE_COLON
                            && self::knownWhileCompiling($scope, $className)),
                ];
            } elseif ($t->text === ')' || $t->text === ']') {
                $closed = \array_pop($brackets);
                $closed !== null && $closed['call'] and $lines += self::argumentLines($closed);
                if ($closed !== null && $closed['type'] === '[' && $inCode && !$inSignature) {
                    if (!$closed['const']) {
                        $lines += $closed['weak'];
                        $brackets === [] or self::markRuntime($brackets);
                    } elseif ((\end($brackets)['type'] ?? null) === '(') {
                        # A constant array passed as an argument is sent from the line it starts on.
                        $lines[$closed['line']] = true;
                    } elseif ($returnArray) {
                        # An empty array returned: nothing else took the `return`.
                        $lines[$closed['line']] = true;
                        $returnArray = false;
                    }
                }
            }

            # An arrow function has no braces: its body ends with the expression holding it.
            $arrow = \end($arrows);
            if ($arrow !== false && $arrow[0] === $depth && (
                \count($brackets) < $arrow[1]
                || (\count($brackets) === $arrow[1] && ($t->text === ',' || $t->text === ';'))
            )) {
                \array_pop($arrows);
            }
            $t->id === \T_FN and $arrows[] = [$depth, \count($brackets)];

            if ($t->is([\T_SWITCH, \T_MATCH])) {
                $header = ['at' => \count($brackets), 'kind' => $t->id === \T_MATCH ? 'match' : 'switch', 'line' => $t->line];
            } elseif ($t->text === ':' && $header !== null && $header['at'] === \count($brackets)) {
                $header = null;
            }

            $t->is(self::RUNTIME) && $brackets !== [] and self::markRuntime($brackets);
            # A class constant folds only when its class is known while compiling: the enclosing or a built-in one.
            $previous?->id === \T_DOUBLE_COLON && $t->id !== \T_CLASS && $brackets !== []
                && !self::knownWhileCompiling($scope, $className) and self::markRuntime($brackets);

            # Arms of a `match`: the conditions before `=>`, separated by commas.
            if ($depth > 0 && $stack[$depth - 1] === 'match' && $brackets === []) {
                $a = &$arms[$depth];
                if ($t->id === \T_DOUBLE_ARROW) {
                    $a = self::endArmCondition($a);
                    $a['inCond'] = false;
                } elseif ($t->text === ',') {
                    $a['inCond'] ? $a = self::endArmCondition($a) : $a['inCond'] = true;
                } elseif ($a['inCond'] && $t->id !== \T_DEFAULT) {
                    $a['cond'][] = $t;
                }
                unset($a);
            }

            $marks = $significant && $inCode && !$inSignature && !$t->is(self::INERT)
                && ($header === null || ($header['kind'] === 'match' && $t->is(self::SUBJECT_OPCODE)));

            if ($marks && $t->is([\T_RETURN, \T_YIELD])) {
                $returnLine = $t->line;
                $marks = false;
            }

            if ($marks && \end($brackets) !== false && \end($brackets)['type'] === '[' && !$t->is(self::RUNTIME)) {
                if ($returnArray) {
                    $returnArray = false;
                } else {
                    # Held until the array closes: a constant array leaves its element lines empty.
                    $brackets[\array_key_last($brackets)]['weak'][$t->line] = true;
                    $marks = false;
                }
            }

            # Arguments of a call: a line that only passes a variable gets no opcode of its own.
            $top = \array_key_last($brackets);
            if ($top !== null && $brackets[$top]['call'] && $t->text !== '(' && $t->text !== '[') {
                if ($t->text === ',') {
                    $lines += self::argumentLines($brackets[$top]);
                    $brackets[$top]['index']++;
                    $brackets[$top]['shape'] = [];
                    $brackets[$top]['held'] = [];
                } else {
                    $brackets[$top]['shape'][] = $t->id;
                    $marks and $brackets[$top]['held'][$t->line] = true;
                    $marks = false;
                }
            }

            if ($inBody) {
                # A `yield` in an arrow function makes that one a generator, not the enclosing function.
                $t->is([\T_YIELD, \T_YIELD_FROM]) && (\end($arrows)[0] ?? null) !== $depth
                    and $generator[$funcDepth] = true;
                $marks and $lines[$t->line] = true;

                if ($significant && ($head[$level] ?? null) === null) {
                    $head[$level] = $t;
                    if ($t->id === \T_IF && $previous?->id !== \T_ELSE) {
                        $compound[$level] = ['kind' => 'if', 'exits' => true, 'else' => false, 'finally' => null];
                    } elseif ($t->id === \T_TRY) {
                        $compound[$level] = ['kind' => 'try', 'exits' => true, 'else' => false, 'finally' => null];
                    } elseif (!$t->is(self::CONTINUATION)) {
                        $compound[$level] = null;
                    }
                }
                if ($t->id === \T_ELSE && ($compound[$level] ?? null) !== null) {
                    $compound[$level]['else'] = true;
                }
                if ($t->id === \T_FINALLY && ($compound[$level] ?? null) !== null) {
                    # Reached from a `try` or `catch` that falls through.
                    $compound[$level]['exits'] or $lines[$t->line] = true;
                }

                # A `goto` label and the `default:` of a `switch` are jump targets, not code.
                if ($t->text === ':' && $brackets === [] && (
                    $previous?->id === \T_DEFAULT
                    || ($previous?->id === \T_STRING && \in_array($beforePrevious?->text, self::STATEMENT_START, true))
                )) {
                    unset($lines[$previous->line]);
                    $head[$level] = null;
                }

                if ($t->text === ';' && $brackets === [] && $level === $depth) {
                    $exits[$level] = ($head[$level] ?? null)?->is([\T_RETURN, \T_THROW]) ?? false;
                    $head[$level] = null;
                    $compound[$level] = null;
                }
            } elseif ($atTop) {
                $marks and $lines[$t->line] = true;
                $t->text === ';' and $topHead = null;
            } elseif (($stack[$depth - 1] ?? null) === 'class' && $brackets === []) {
                $t->id === \T_VARIABLE and $property = true;
                $t->text === ';' and $property = false;
            }

            $beforePrevious = $previous;
            $previous = $t;
        }

        $returnLine === null or $lines[$returnLine] = true;
        \ksort($lines);

        return $lines;
    }

    /**
     * Depth of the innermost function body enclosing the current token, or null when a class body or a
     * constant-expression closure is closer: drivers report no lines there.
     *
     * @param list<string> $stack
     * @return int<1, max>|null
     */
    private static function funcDepth(array $stack): ?int
    {
        for ($i = \count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i] === 'func') {
                return $i + 1;
            }
            if ($stack[$i] === 'class' || $stack[$i] === 'constant') {
                return null;
            }
        }

        return null;
    }

    /**
     * Whether the last statement of a block leaves it, so the code after the block is unreachable.
     *
     * @param array{kind: string, exits: bool, else: bool, finally: bool|null}|null $compound
     */
    private static function statementExits(bool $exits, ?array $compound): bool
    {
        return match ($compound['kind'] ?? null) {
            'if' => $compound['exits'] && $compound['else'],
            'try' => $compound['exits'] || $compound['finally'] === true,
            default => $exits,
        };
    }

    /**
     * Closes the condition of a `match` arm being read and records whether it suits a jump table.
     *
     * @param array{line: int, keys: int, type: int|null, table: bool, cond: list<\PhpToken>, inCond: bool} $arms
     * @return array{line: int, keys: int, type: int|null, table: bool, cond: list<\PhpToken>, inCond: bool}
     */
    private static function endArmCondition(array $arms): array
    {
        $cond = $arms['cond'];
        $arms['cond'] = [];
        if ($cond === []) {
            return $arms;
        }

        $key = \count($cond) === 1 && $cond[0]->is(self::JUMP_KEY) ? $cond[0]->id : null;
        if ($key === null || ($arms['type'] !== null && $arms['type'] !== $key)) {
            $arms['table'] = false;
        }
        $arms['type'] = $key;
        $arms['keys']++;

        return $arms;
    }

    /**
     * Lines of the argument just read that carry an opcode.
     *
     * Passing a variable, plain or named, gets no line of its own unless it is the first argument or the
     * callee is bound while compiling: a fully qualified built-in function, or a static method of a class
     * {@see knownWhileCompiling() known} at that moment.
     *
     * @param array{index: int, shape: list<int>, held: array<int, true>, resolved: bool} $call
     * @return array<int, true>
     */
    private static function argumentLines(array $call): array
    {
        $shape = $call['shape'];
        $passesVariable = $shape === [\T_VARIABLE]
            || (\count($shape) === 3 && $shape[1] === \ord(':') && $shape[2] === \T_VARIABLE);

        return $passesVariable && $call['index'] > 0 && !$call['resolved'] ? [] : $call['held'];
    }

    /**
     * The next token after `$i` that is not whitespace or a comment.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function next(array $tokens, int $i): ?\PhpToken
    {
        while (isset($tokens[++$i])) {
            if (!$tokens[$i]->isIgnorable()) {
                return $tokens[$i];
            }
        }

        return null;
    }

    /**
     * Whether PHP knows the class before `::` while compiling, so its constant folds into the value and
     * a call of its method is bound in advance.
     *
     * Only the class being declared and built-in classes qualify: whether any other class is loaded at
     * that moment depends on the run. A built-in enum does not qualify, since its cases are objects.
     *
     * @param \PhpToken|null $class The token before `::`.
     * @param string|null $declared Name of the class being declared.
     */
    private static function knownWhileCompiling(?\PhpToken $class, ?string $declared): bool
    {
        if ($class === null || !$class->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED])) {
            return false;
        }
        $name = \ltrim($class->text, '\\');
        if (\strcasecmp($name, 'self') === 0 || ($declared !== null && \strcasecmp($name, $declared) === 0)) {
            return true;
        }

        if (!\class_exists($name, false) && !\interface_exists($name, false)) {
            return false;
        }
        $reflection = new \ReflectionClass($name);

        return $reflection->isInternal() && !$reflection->isEnum();
    }

    /**
     * Whether a fully qualified function name is a built-in function, bound while compiling.
     */
    private static function builtInFunction(string $name): bool
    {
        $name = \ltrim($name, '\\');

        return \function_exists($name) && (new \ReflectionFunction($name))->isInternal();
    }

    /**
     * Marks the innermost open array as built at runtime.
     *
     * @param list<array{type: '('|'[', line: int, const: bool, weak: array<int, true>, call: bool, index: int, shape: list<int>, held: array<int, true>, resolved: bool}> $brackets
     */
    private static function markRuntime(array &$brackets): void
    {
        for ($i = \count($brackets) - 1; $i >= 0; $i--) {
            if ($brackets[$i]['type'] === '[') {
                $brackets[$i]['const'] = false;
                return;
            }
        }
    }
}
