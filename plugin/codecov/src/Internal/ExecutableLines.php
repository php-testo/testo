<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

/**
 * Estimates the executable lines of PHP source from its tokens, without loading it.
 *
 * Stands in for a coverage driver on files no test loaded, so they reach the report with every line
 * unexecuted. A line counts only when every driver reports it once the file is loaded, on every PHP version
 * from 8.2: Xdebug, PCOV and turbocov. Those are lines where an operation starts, such as a call, an
 * assignment or a jump, plus a reachable closing brace that carries an implicit `return`, inline HTML and
 * top-level statements other than declarations. Where the line of an opcode depends on how an expression
 * is laid out, on what is loaded at the time or on the PHP version, the line is left out.
 *
 * The estimate never adds a line a driver would not report: reports of several runs merged line by line,
 * across drivers and PHP versions, keep such a line uncovered even after another run loads the file. A
 * line left out costs nothing once a run loads the file.
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

    /** Keywords that compile to an opcode on their own line. */
    private const ACTION = [
        \T_NEW, \T_THROW, \T_INC, \T_DEC, \T_BREAK, \T_CONTINUE, \T_GOTO, \T_CATCH,
    ];

    /** Keywords that consume the value of the expression after them. */
    private const CONSUMER = [
        \T_CLONE, \T_ECHO, \T_PRINT, \T_EXIT, \T_EVAL, \T_INCLUDE, \T_INCLUDE_ONCE, \T_REQUIRE, \T_REQUIRE_ONCE,
    ];

    /** Compound assignments, emitted once their value is computed. */
    private const ASSIGNMENT = [
        \T_PLUS_EQUAL, \T_MINUS_EQUAL, \T_MUL_EQUAL, \T_DIV_EQUAL, \T_CONCAT_EQUAL, \T_MOD_EQUAL,
        \T_AND_EQUAL, \T_OR_EQUAL, \T_XOR_EQUAL, \T_SL_EQUAL, \T_SR_EQUAL, \T_POW_EQUAL, \T_COALESCE_EQUAL,
    ];

    /** Binary operators, `instanceof` and the `?` of a ternary. */
    private const BINARY = [
        \T_BOOLEAN_AND, \T_BOOLEAN_OR, \T_LOGICAL_AND, \T_LOGICAL_OR, \T_LOGICAL_XOR, \T_COALESCE,
        \T_IS_EQUAL, \T_IS_NOT_EQUAL, \T_IS_IDENTICAL, \T_IS_NOT_IDENTICAL, \T_IS_SMALLER_OR_EQUAL,
        \T_IS_GREATER_OR_EQUAL, \T_SPACESHIP, \T_SL, \T_SR, \T_POW, \T_INSTANCEOF,
        '+', '-', '*', '/', '%', '.', '&', '|', '^', '<', '>', '?',
    ];

    /** Binary operators that jump past their right operand, emitted right after the left one. */
    private const SHORT_CIRCUIT = [\T_BOOLEAN_AND, \T_BOOLEAN_OR, \T_LOGICAL_AND, \T_LOGICAL_OR, \T_COALESCE, '?'];

    /** Tokens that end an operand. */
    private const OPERAND_END = [
        \T_VARIABLE, \T_LNUMBER, \T_DNUMBER, \T_CONSTANT_ENCAPSED_STRING, \T_STRING, \T_NAME_QUALIFIED,
        \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE, \T_CLASS, \T_LINE, \T_FILE, \T_DIR, \T_CLASS_C, \T_TRAIT_C,
        \T_METHOD_C, \T_FUNC_C, \T_NS_C, ')', ']', '"', \T_END_HEREDOC,
    ];

    /** Operands PHP may fold into a constant while compiling. */
    private const FOLDABLE = [
        \T_LNUMBER, \T_DNUMBER, \T_CONSTANT_ENCAPSED_STRING, \T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED,
        \T_NAME_RELATIVE, \T_CLASS, \T_LINE, \T_FILE, \T_DIR, \T_CLASS_C, \T_TRAIT_C, \T_METHOD_C, \T_FUNC_C, \T_NS_C,
    ];

    /** Casts, emitted once their operand is computed. */
    private const CAST = [
        \T_INT_CAST, \T_DOUBLE_CAST, \T_STRING_CAST, \T_ARRAY_CAST, \T_OBJECT_CAST, \T_BOOL_CAST,
    ];

    /** Tokens a chain of property fetches is built of. */
    private const CHAIN = [
        \T_VARIABLE, \T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE, \T_STATIC,
        \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON,
    ];

    /** Openers of brackets that pair with `)`, `]` or `}`. */
    private const OPENER = ['(', '[', '{', '#[', '${'];

    /**
     * Functions PHP compiles into dedicated opcodes, which take the lines of the arguments rather than the
     * line of the call; `sprintf()` since PHP 8.4. Compared in lower case.
     */
    private const COMPILED_FUNCTIONS = [
        'strlen', 'is_null', 'is_bool', 'is_long', 'is_int', 'is_integer', 'is_float', 'is_double', 'is_string',
        'is_array', 'is_object', 'is_resource', 'is_scalar', 'boolval', 'intval', 'floatval', 'doubleval',
        'strval', 'defined', 'chr', 'ord', 'call_user_func_array', 'call_user_func', 'in_array', 'count',
        'sizeof', 'get_class', 'get_called_class', 'gettype', 'func_num_args', 'func_get_args', 'array_slice',
        'array_key_exists', 'sprintf',
    ];

    /** Literals a `match` can dispatch on through a jump table. */
    private const JUMP_KEY = [\T_LNUMBER, \T_CONSTANT_ENCAPSED_STRING];

    /** Heads of top-level statements that compile to no opcode. */
    private const DECLARATION = [
        \T_NAMESPACE, \T_USE, \T_DECLARE, \T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM,
        \T_ABSTRACT, \T_FINAL, \T_READONLY, \T_FUNCTION, \T_ATTRIBUTE, \T_CONST,
    ];

    /** Heads of statements that never let the next one in the block run. */
    private const JUMP = [\T_RETURN, \T_THROW, \T_GOTO, \T_BREAK, \T_CONTINUE, \T_EXIT];

    /** Keywords that continue a compound statement rather than starting a new one. */
    private const CONTINUATION = [\T_ELSE, \T_ELSEIF, \T_CATCH, \T_FINALLY];

    /** Tokens after which a name followed by `:` is a `goto` label. */
    private const STATEMENT_START = [';', '{', '}', ':'];

    /** Tokens after which a declaration keyword is a name: of a method, a class member or a property. */
    private const NAME_PREFIX = [\T_FUNCTION, \T_DOUBLE_COLON, \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR];

    /** Shapes of an argument that passes a literal or a constant. */
    private const LITERAL_ARGUMENT = [
        [\T_CONSTANT_ENCAPSED_STRING], [\T_LNUMBER], [\T_DNUMBER], [\T_STRING], [\T_NAME_FULLY_QUALIFIED],
        [\T_STRING, \T_DOUBLE_COLON, \T_STRING], [\T_STRING, \T_DOUBLE_COLON, \T_CLASS],
        [\T_NAME_QUALIFIED, \T_DOUBLE_COLON, \T_STRING], [\T_NAME_QUALIFIED, \T_DOUBLE_COLON, \T_CLASS],
        [\T_NAME_FULLY_QUALIFIED, \T_DOUBLE_COLON, \T_STRING], [\T_NAME_FULLY_QUALIFIED, \T_DOUBLE_COLON, \T_CLASS],
    ];

    /** Tokens after which `(` opens the argument list of a call. */
    private const CALLEE = [
        \T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE, \T_VARIABLE, \T_STATIC,
        ')', ']', '}',
    ];

    /**
     * Matching brackets of the source being scanned, so that finding the end of a call or a condition does
     * not walk its tokens again for every nested one.
     *
     * @var array<int, int>
     */
    private static array $pairs = [];

    /**
     * @return array<int<1, max>, true> Line number => true.
     */
    public static function of(string $source): array
    {
        return self::scan($source)[0];
    }

    /**
     * Closing braces of function bodies that every driver lets the code fall through to, where the implicit
     * `return` runs.
     *
     * Unlike {@see of()} this answers for a file a driver did load, so it leaves out nothing it cannot rule
     * out, whatever line a driver reports elsewhere.
     *
     * @return array<int<1, max>, true> Line number => true.
     */
    public static function bodyEnds(string $source): array
    {
        return self::scan($source)[1];
    }

    /**
     * @return array{array<int<1, max>, true>, array<int<1, max>, true>} The estimate and the body ends.
     */
    private static function scan(string $source): array
    {
        $lines = [];
        $ends = [];

        # Contexts opened by `{`: 'func' (a function body), 'constant' (a body drivers do not report: a closure
        # in a constant expression or a property hook), 'class', 'hooks', 'block', 'switch' and 'match' (their
        # bodies), 'interp' (`{$` in a string or a member named by an expression).
        $stack = [];
        # Open `(` and `[` of the current brace context, saved and restored around nested ones. An array
        # entry collects the lines of its constant elements until it closes and shows whether it folds; a
        # call entry reads its arguments one by one.
        /** @var list<array{type: '('|'[', line: int, const: bool, weak: array<int, true>, call: bool, shape: list<int>, dynamic: bool, from: int|null, to: int|null}> $brackets */
        $brackets = [];
        $savedBrackets = [];

        # What the next `{` opens, once a declaration keyword is seen, and for a function whether it is
        # an anonymous one in a constant expression.
        $pending = null;
        $parens = 0;
        $constant = false;
        $anonymous = null;
        $resumes = false;
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
        # Index of the last token of a code region that may compile to no opcode.
        $muted = -1;
        # Per brace depth: whether a string opened by a quote is being read.
        $quotes = [];
        # Bracket count of the arrow function whose signature is being read.
        $arrowSignature = null;
        # Unreachable code being read: its statement depth and first line; and the line ranges read so far.
        $dead = null;
        $deadRanges = [];
        # Per depth of a `switch` body: whether it has a `default` and whether every way out of it returns.
        $switches = [];
        # Per brace depth: whether it is the body of a loop.
        $loops = [];
        # Per statement depth: whether the statement has an `?? throw`.
        $coalesceThrows = [];
        # Per statement depth: whether the last statement leaves only by an `?? throw`.
        $coalesceExit = [];
        # Whether the `while` or `for` being read never ends by its condition.
        $endless = false;
        # What the class being declared is; per class body depth the methods a call on `$this` binds while
        # compiling, null for a trait; per function body depth whether it is a closure.
        $classKind = null;
        $bound = [];
        $closures = [];
        # Whether a namespace is open, so an unqualified function is resolved at runtime unless imported.
        $namespaced = false;
        $importsFunctions = false;

        # Per statement depth: the first token of the current statement, whether the last finished one
        # leaves the block, and the `if` or `try` it continues.
        $head = [];
        $exits = [];
        $compound = [];
        # Per `match` body depth: what its arms dispatch on.
        $arms = [];
        $topHead = null;

        $tokens = \PhpToken::tokenize($source);
        $use = false;
        foreach ($tokens as $i => $t) {
            # Text inside a string or around the tags may read `(`, `,` or `;`: a leading NUL keeps it from
            # passing for punctuation, and keeps its line breaks.
            $t->is([\T_ENCAPSED_AND_WHITESPACE, \T_INLINE_HTML]) and $t->text = "\0" . $t->text;
            # A `use function`, plain or in a group, imports a function by its unqualified name.
            $t->id === \T_USE && self::next($tokens, $i)?->text !== '(' and $use = true;
            $use && $t->id === \T_FUNCTION and $importsFunctions = true;
            $t->text === ';' and $use = false;
        }
        self::$pairs = self::pairs($tokens);
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

            # A heredoc that interpolates is assembled at runtime, starting on the line of its first text part
            # when that part fits on one line; a longer one moves the start.
            if ($t->id === \T_START_HEREDOC) {
                $heredocText = null;
            } elseif ($t->id === \T_END_HEREDOC) {
                $heredocText = false;
            } elseif ($t->id === \T_ENCAPSED_AND_WHITESPACE) {
                $heredocText ??= \str_contains($t->text, "\n") ? false : $t->line;
            } elseif (\is_int($heredocText)) {
                $inCode && $dead === null && $i > $muted and $lines[$heredocText] = true;
                $heredocText = false;
            }

            # A `return` or `yield` takes the line of its value, except that an array literal or a `match`
            # puts it on the line of the first element or arm.
            if ($returnLine !== null) {
                if ($t->text === '[' || $t->is([\T_ARRAY, \T_MATCH])) {
                    $returnArray = $t->id !== \T_MATCH;
                } elseif (self::endLine($tokens, $i) === $t->line) {
                    # A value spread over lines puts the opcode on a line that depends on its shape: leave it
                    # to the operations inside.
                    $lines[$t->text === ';' ? $returnLine : $t->line] = true;
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
            $t->id === \T_NAMESPACE && $atTop and $namespaced = self::next($tokens, $i)?->text !== '{';
            $t->id === \T_STRING && $previous?->is(self::CLASS_LIKE) and $className = $t->text;

            # The lexer emits `T_ENUM` only before a name, so a soft-keyword `enum` never lands here.
            if ($isName) {
                $anonymous === null && $pending === 'func' and $anonymous = false;
            } elseif ($t->is(self::CLASS_LIKE)) {
                $pending = 'class';
                $classKind = $t->id;
            } elseif ($t->id === \T_FUNCTION) {
                # A private or final method declared so far is bound while compiling when called on `$this`.
                ($stack[$depth - 1] ?? null) === 'class' && isset($bound[$depth]) && self::bindsOnThis($tokens, $i)
                    and $bound[$depth][\strtolower((string) self::methodName($tokens, $i))] = true;
                # A closure as a default value suspends the signature it sits in until it closes.
                $resumes = $pending === 'func';
                $resumes and $suspended[] = [$parens, $constant, $anonymous];
                # A closure outside running code belongs to a constant expression: an attribute, a constant
                # or a default value. Drivers never report its body.
                $constant = !$inCode || $resumes;
                $anonymous = null;
                $pending = 'func';
                $parens = 0;
            } elseif ($anonymous === null && $pending === 'func' && $t->text !== '&') {
                # A method may be named by a keyword (`print`, `array`), so only `(` tells a closure.
                $anonymous = $t->text === '(';
            }

            # A `while` or `for` whose condition is always true leaves only through a `break`.
            $t->is([\T_WHILE, \T_FOR]) and $endless = self::endless($tokens, $i);

            # Parameters, `use` and the return type compile to no opcode on their own lines; those of an arrow
            # function run up to its `=>`.
            $t->id === \T_DOUBLE_ARROW && $arrowSignature === \count($brackets) and $arrowSignature = null;
            $inSignature = ($pending === 'func' && $t->id !== \T_FUNCTION) || ($arrowSignature !== null && $t->id !== \T_FN);
            $t->id === \T_FN and $arrowSignature = \count($brackets);
            if ($pending === 'func') {
                $t->text === '(' and $parens++;
                $t->text === ')' and $parens--;
                # A body-less method: abstract or declared by an interface.
                $t->text === ';' && $parens === 0 and $pending = null;
            }

            # `T_CURLY_OPEN` reads `{` too, so interpolation is told apart by id.
            $interpolation = $t->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES]);
            if ($t->text === '{' || $interpolation) {
                $brackets !== [] && !$interpolation and self::markRuntime($brackets);
                # A member or variable named by an expression, `$o->{$name}`, is one more part of it.
                $interpolation = $interpolation || $previous?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON])
                    || $previous?->text === '$';
                if ($interpolation) {
                    $stack[] = 'interp';
                } elseif ($pending === 'func' && $parens > 0) {
                    # Hooks of a promoted parameter: the signature resumes after them.
                    $stack[] = 'hooks';
                    $suspended[] = [$parens, $constant, $anonymous];
                    $resume[$depth + 1] = true;
                    $pending = null;
                } elseif ($property && $pending === null && ($stack[$depth - 1] ?? null) === 'class') {
                    $stack[] = 'hooks';
                    $resume[$depth + 1] = false;
                } elseif ($pending === null && ($stack[$depth - 1] ?? null) === 'hooks') {
                    # The body of a property hook: PCOV before PHP 8.6 leaves it out, Xdebug reports it only once run.
                    $stack[] = 'constant';
                    $resume[$depth + 1] = false;
                } elseif ($pending === 'func' && $parens === 0 && $constant && $anonymous) {
                    $stack[] = 'constant';
                    $resume[$depth + 1] = $resumes;
                    $pending = null;
                } elseif ($pending !== null && $parens === 0) {
                    $stack[] = $pending;
                    # The scope of a trait is unknown while compiling, so nothing is bound in it.
                    $pending === 'class' and $bound[$depth + 1] = $classKind === \T_TRAIT ? null : [];
                    $pending === 'func' and $closures[$depth + 1] = $anonymous === true;
                    $pending = null;
                } elseif ($header !== null && $header['kind'] === 'match' && $header['at'] === \count($brackets)) {
                    $stack[] = 'match';
                    $arms[$depth + 1] = ['line' => $header['line'], 'keys' => 0, 'type' => null, 'table' => true, 'cond' => [], 'inCond' => true];
                } elseif ($header !== null && $header['kind'] === 'switch' && $header['at'] === \count($brackets)) {
                    $stack[] = 'switch';
                    $switches[$depth + 1] = ['default' => false, 'exits' => true];
                } else {
                    # A block at the top level, such as `namespace X { … }`, holds statements of its own.
                    $atTop and $topHead = null;
                    $stack[] = 'block';
                    $loops[$depth + 1] = ($head[$level] ?? null)?->is([\T_FOR, \T_FOREACH, \T_WHILE, \T_DO])
                        ? ['endless' => $endless && ($head[$level] ?? null)?->is([\T_FOR, \T_WHILE]), 'breaks' => false]
                        : null;
                }
                $endless = false;
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
                # Whether the body leaves in the eyes of every driver, Xdebug's reading of `?? throw` aside.
                $leavesForAll = $leaves && !($coalesceExit[$depth] ?? false);
                $loop = $loops[$depth] ?? null;
                unset($head[$depth], $exits[$depth], $compound[$depth], $coalesceThrows[$depth], $coalesceExit[$depth],
                    $loops[$depth], $bound[$depth], $closures[$depth]);

                $property = false;

                # Unreachable code ends with the block it is in; the brace of a body it ends is unreachable too.
                if ($dead !== null && $dead['level'] === $depth) {
                    $deadRanges[] = [$dead['from'], $t->line];
                    $leavesForAll = !$dead['coalesce'];
                    $dead = null;
                    $leaves = true;
                }

                if ($closing === 'constant' || $closing === 'hooks') {
                    if ($resume[$depth]) {
                        [$parens, $constant, $anonymous] = \array_pop($suspended);
                        $pending = 'func';
                    }
                    unset($resume[$depth]);
                } elseif ($closing === 'func') {
                    # Falling off the end of a body runs an implicit `return` on its closing brace.
                    $leaves or $lines[$t->line] = true;
                    $leavesForAll or $ends[$t->line] = true;
                    # Xdebug sends a closure passed to a call from the line it ends on.
                    $brackets !== [] && \end($brackets)['call'] and $ends[$t->line] = true;
                } elseif ($closing === 'match') {
                    $a = self::endArmCondition($arms[$depth]);
                    # Literal keys of one type let PHP dispatch through a table, an opcode on the subject line.
                    $a['table'] && $a['keys'] >= 2 and $lines[$a['line']] = true;
                    unset($arms[$depth]);
                } elseif ($closing === 'switch') {
                    # With a `default` and every case group leaving, nothing gets past the `switch`.
                    $s = $switches[$depth];
                    unset($switches[$depth]);
                    $parent = $depth - 1;
                    $compound[$parent] = null;
                    $exits[$parent] = $s['default'] && $s['exits'] && $leaves;
                    $coalesceExit[$parent] = $exits[$parent] && !$leavesForAll;
                    $head[$parent] = null;
                } elseif ($closing === 'block') {
                    $parent = $depth - 1;
                    $opener = $head[$parent] ?? null;
                    if (($compound[$parent] ?? null) !== null && $opener?->is([\T_IF, \T_ELSEIF, \T_ELSE, \T_TRY, \T_CATCH, \T_FINALLY])) {
                        $opener->id === \T_FINALLY
                            ? $compound[$parent]['finally'] = $leaves
                            : $compound[$parent]['exits'] = $compound[$parent]['exits'] && $leaves;
                    } else {
                        $compound[$parent] = null;
                        # Nothing gets past an endless loop no `break` leaves.
                        $exits[$parent] = $loop !== null && $loop['endless'] && !$loop['breaks'];
                        $coalesceExit[$parent] = false;
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
                $stack[] = 'constant';
                $shortHooks[] = $depth + 1;
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
                unset($head[$depth], $exits[$depth], $compound[$depth]);
                while (\end($arrows) !== false && \end($arrows)[0] >= $depth) {
                    \array_pop($arrows);
                }
                $beforePrevious = $previous;
                $previous = $t;
                continue;
            }

            # Arguments of an attribute are a constant expression.
            $t->id === \T_ATTRIBUTE and $muted = \max($muted, self::closer($tokens, $i));

            if ($t->text === '(' || $t->text === '[' || $t->id === \T_ATTRIBUTE) {
                $isArray = $t->text !== '(' || $previous?->id === \T_ARRAY;
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
                    'shape' => [],
                    # Inside an array literal the arguments of a call are sent from lines of their own, and
                    # inside code compiled out they are not sent at all.
                    'dynamic' => !$isArray && !$inSignature && $inCode && $i > $muted
                        && !\in_array('[', \array_column($brackets, 'type'), true)
                        && self::boundAtRuntime($tokens, $i, self::thisMethods($stack, $bound, $closures, $arrows), $namespaced && !$importsFunctions),
                    'from' => null,
                    'to' => null,
                ];
            } elseif ($t->text === ')' || $t->text === ']') {
                $closed = \array_pop($brackets);
                $closed !== null && $closed['call'] && $dead === null && $i > $muted and $lines += self::argumentLine($closed);
                if ($closed !== null && $closed['type'] === '[' && $inCode && !$inSignature) {
                    if (!$closed['const']) {
                        $lines += $closed['weak'];
                        $brackets === [] or self::markRuntime($brackets);
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

            # A statement after one that leaves the block cannot run, up to a label a jump may reach.
            if ($inBody && $significant && ($head[$level] ?? null) === null) {
                $label = $t->is([\T_CASE, \T_DEFAULT]) || ($t->id === \T_STRING && self::next($tokens, $i)?->text === ':');
                if ($dead === null && !$label && !$t->is(self::CONTINUATION)
                    && self::statementExits($exits[$level] ?? false, $compound[$level] ?? null)
                ) {
                    $dead = ['level' => $level, 'from' => $t->line, 'coalesce' => $coalesceExit[$level] ?? false];
                } elseif ($dead !== null && $label && $dead['level'] === $level) {
                    $deadRanges[] = [$dead['from'], $t->line - 1];
                    $dead = null;
                }
            }

            # A `break` leaves the innermost loop or `switch`, `break 2` the next one too; a `continue` leaves
            # a `switch` the same way but stays in a loop.
            if ($t->is([\T_BREAK, \T_CONTINUE])) {
                $numbered = self::next($tokens, $i)?->id === \T_LNUMBER;
                for ($d = $depth; $d > 0; $d--) {
                    if ($stack[$d - 1] === 'switch') {
                        $switches[$d]['exits'] = false;
                    } elseif (isset($loops[$d])) {
                        $t->id === \T_BREAK and $loops[$d]['breaks'] = true;
                    } else {
                        continue;
                    }
                    if (!$numbered) {
                        break;
                    }
                }
            }

            # A quote closes the string it opened at the same depth; the closing one starts nothing.
            $closingQuote = false;
            if ($t->text === '"' || $t->text === '`') {
                $closingQuote = $quotes[$depth] ?? false;
                $quotes[$depth] = !$closingQuote;
            }

            # The `:` of a ternary is the only punctuation that may start an operation.
            $code = ($significant || ($t->text === ':' && self::ternaryColon($tokens, $i)))
                && $inCode && !$inSignature && !$t->is(self::INERT) && $i > $muted && $dead === null;

            # `zend.assertions=-1` compiles an assertion out, and runs merged into one report may differ in it.
            if ($code && self::callsAssert($tokens, $i)) {
                $muted = self::closer($tokens, self::nextIndex($tokens, $i));
                $code = false;
            }

            # A logical operator after a constant operand may be decided while compiling, dropping the rest.
            if ($code && $t->is([\T_BOOLEAN_AND, \T_BOOLEAN_OR, \T_LOGICAL_AND, \T_LOGICAL_OR]) && self::constantBefore($tokens, $i)) {
                $muted = self::endIndex($tokens, self::nextIndex($tokens, $i));
                $code = false;
            }

            # An `if` decided while compiling drops the branch not taken, and the decision may differ between
            # the PHP versions of runs merged into one report.
            if ($code && $t->is([\T_IF, \T_ELSEIF]) && self::foldableCondition($tokens, $i)) {
                $muted = \max($muted, self::chainEnd($tokens, $i));
                $code = false;
            }

            if ($code && $t->is([\T_RETURN, \T_YIELD])) {
                $returnLine = $t->line;
                $code = false;
            }

            # A line is executable when an operation starts on it: an operand alone leaves its value to the
            # operation that consumes it, on the line that operation starts on.
            $marks = $code && !$closingQuote && (
                self::operates($tokens, $i, $previous)
                # The result of a `match` arm, when it fits on one line.
                || ($depth > 0 && $stack[$depth - 1] === 'match' && $brackets === [] && $previous?->id === \T_DOUBLE_ARROW
                    && self::endLine($tokens, $i) === $t->line)
            );

            $open = \end($brackets);
            # Inside an array literal the operations of an element are emitted in an order of its own.
            $open !== false && $open['type'] === '[' && ($t->is(self::BINARY) || $t->text === ':') and $marks = false;
            # An element of an array built at runtime, `key => $value`, is added on its line unless the array
            # is an element of another one.
            $code && $open !== false && $open['type'] === '[' && $t->id === \T_VARIABLE
                && $previous?->id === \T_DOUBLE_ARROW && $previous->line === $t->line
                && \in_array(self::next($tokens, $i)?->text, [',', ']'], true)
                && (($brackets[\count($brackets) - 2] ?? null)['type'] ?? null) !== '['
                and $marks = true;
            if ($code && $returnArray && $open !== false && $open['type'] === '[') {
                # The first element of a returned array literal carries the `return`, when it fits on its line.
                $returnArray = false;
                self::endLine($tokens, $i) === $t->line and $lines[$t->line] = true;
                $marks = false;
            } elseif ($marks && $open !== false && $open['type'] === '[' && !$t->is(self::RUNTIME)) {
                # Held until the array closes: a constant array leaves its element lines empty.
                $brackets[\array_key_last($brackets)]['weak'][$t->line] = true;
                $marks = false;
            }

            # Arguments of a call: what each one is built of, and the lines it spans.
            $top = \array_key_last($brackets);
            if ($top !== null && $brackets[$top]['call'] && $t->text !== '(' && $t->text !== '[') {
                if ($t->text === ',') {
                    $dead === null and $lines += self::argumentLine($brackets[$top]);
                    $brackets[$top]['shape'] = [];
                    $brackets[$top]['from'] = $brackets[$top]['to'] = null;
                } else {
                    $brackets[$top]['shape'][] = $t->id;
                    $brackets[$top]['from'] ??= $t->line;
                    $brackets[$top]['to'] = $t->line + \substr_count($t->text, "\n");
                }
            }

            if ($inBody) {
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
                # `else if` goes on with the chain, as `elseif` does.
                if ($t->id === \T_ELSE && ($compound[$level] ?? null) !== null && self::next($tokens, $i)?->id !== \T_IF) {
                    $compound[$level]['else'] = true;
                }
                if ($t->id === \T_FINALLY && ($compound[$level] ?? null) !== null) {
                    # Reached from a `try` or `catch` that falls through.
                    $compound[$level]['exits'] or $lines[$t->line] = true;
                }

                # Xdebug takes the code after `?? throw` for unreachable.
                $t->id === \T_THROW && $previous?->id === \T_COALESCE and $coalesceThrows[$level] = true;

                # A `goto` label and the `default:` of a `switch` are jump targets, not code.
                if ($t->text === ':' && $brackets === [] && (
                    $previous?->id === \T_DEFAULT
                    || ($previous?->id === \T_STRING && \in_array($beforePrevious?->text, self::STATEMENT_START, true))
                )) {
                    unset($lines[$previous->line]);
                    $head[$level] = null;
                }

                # A `case` or `default` label starts a group of statements reached by a jump.
                if ($t->text === ':' && $brackets === [] && ($stack[$level - 1] ?? null) === 'switch'
                    && ($previous?->id === \T_DEFAULT || ($head[$level] ?? null)?->id === \T_CASE)
                ) {
                    $previous?->id === \T_DEFAULT and $switches[$level]['default'] = true;
                    $head[$level] = null;
                    $exits[$level] = $coalesceExit[$level] = false;
                    $compound[$level] = null;
                }

                if ($t->text === ';' && $brackets === [] && $level === $depth) {
                    $jumps = ($head[$level] ?? null)?->is(self::JUMP) ?? false;
                    $coalesceExit[$level] = !$jumps && isset($coalesceThrows[$level]);
                    $exits[$level] = $jumps || $coalesceExit[$level];
                    unset($coalesceThrows[$level]);
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
        $dead === null or $deadRanges[] = [$dead['from'], \PHP_INT_MAX];
        foreach ($deadRanges as [$from, $to]) {
            $lines = \array_filter($lines, static fn(int $line): bool => $line < $from || $line > $to, \ARRAY_FILTER_USE_KEY);
        }
        \ksort($lines);
        self::$pairs = [];

        return [$lines, $ends];
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
     * The line of the argument just read when sending it is an opcode of its own.
     *
     * A callee bound only at runtime is sent a literal by an opcode on the literal's line. A callee PHP may
     * bind while compiling is sent it by an opcode that drivers leave out, and a variable is sent to any
     * callee that way.
     *
     * @param array{shape: list<int>, dynamic: bool, from: int|null, to: int|null} $call
     * @return array<int, true>
     */
    private static function argumentLine(array $call): array
    {
        $shape = $call['shape'];
        # A named argument, `name: value`.
        \count($shape) > 2 && $shape[0] === \T_STRING && $shape[1] === \ord(':') and $shape = \array_slice($shape, 2);
        $literal = \in_array($shape, self::LITERAL_ARGUMENT, true)
            || (($shape[0] ?? null) === \ord('-') && \in_array(\array_slice($shape, 1), [[\T_LNUMBER], [\T_DNUMBER]], true));

        return $call['dynamic'] && $literal && $call['from'] !== null && $call['from'] === $call['to']
            ? [$call['from'] => true]
            : [];
    }

    /**
     * Whether the call whose argument list opens at `$open` is bound only at runtime.
     *
     * PHP binds while compiling a function or a static method it finds loaded, which depends on the run,
     * and a method called on `$this` that is private or final and declared above; since PHP 8.6 also the
     * constructor of a loaded class. A closure in a variable, a method of another object, `parent::` and
     * `static::` calls and, in a namespace, an unqualified function not imported are left to runtime.
     *
     * @param list<\PhpToken> $tokens
     * @param array<string, true>|null $thisMethods Methods a call on `$this` binds, or null when none.
     * @param bool $fallback Whether an unqualified function name is resolved at runtime.
     */
    private static function boundAtRuntime(array $tokens, int $open, ?array $thisMethods, bool $fallback): bool
    {
        # The open tag always comes first, so a bracket has a token before it.
        $c = self::previousIndex($tokens, $open);
        $callee = $tokens[$c];
        if ($callee->id === \T_VARIABLE) {
            return true;
        }

        $before = $tokens[$b = self::previousIndex($tokens, $c)] ?? null;
        if ($callee->id !== \T_STRING || $before?->id === \T_NEW) {
            return false;
        }
        if ($before?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR])) {
            return ($tokens[self::previousIndex($tokens, $b)] ?? null)?->text !== '$this'
                || $thisMethods === null
                || !isset($thisMethods[\strtolower($callee->text)]);
        }
        if ($before?->id === \T_DOUBLE_COLON) {
            $class = $tokens[self::previousIndex($tokens, $b)] ?? null;

            return $class?->id === \T_STATIC || \strcasecmp((string) $class?->text, 'parent') === 0;
        }

        return $fallback && !$before?->is([\T_FUNCTION, \T_FN]) && \strcasecmp($callee->text, 'assert') !== 0;
    }

    /**
     * Methods a call on `$this` binds while compiling at the current token, or null when the scope is not
     * known then: outside a class, in a trait or in a closure, which may be bound to another object.
     *
     * @param list<string> $stack
     * @param array<int, array<string, true>|null> $bound
     * @param array<int, bool> $closures
     * @param list<array{int, int}> $arrows
     * @return array<string, true>|null
     */
    private static function thisMethods(array $stack, array $bound, array $closures, array $arrows): ?array
    {
        if ($arrows !== []) {
            return null;
        }
        for ($d = \count($stack); $d > 0; $d--) {
            if ($stack[$d - 1] === 'func' && ($closures[$d] ?? false)) {
                return null;
            }
            if ($stack[$d - 1] === 'class') {
                return $bound[$d] ?? null;
            }
        }

        return null;
    }

    /**
     * Whether the method declared by the `function` at `$i` is private or final.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function bindsOnThis(array $tokens, int $i): bool
    {
        while (($i = self::previousIndex($tokens, $i)) >= 0
            && $tokens[$i]->is([\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_FINAL, \T_STATIC, \T_ABSTRACT, \T_READONLY])
        ) {
            if ($tokens[$i]->is([\T_PRIVATE, \T_FINAL])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Name of the function declared by the `function` at `$i`, or null for a closure.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function methodName(array $tokens, int $i): ?string
    {
        $name = self::next($tokens, $i);
        $name?->text === '&' and $name = self::next($tokens, self::nextIndex($tokens, $i));

        return $name === null || $name->text === '(' ? null : $name->text;
    }

    /**
     * The next token after `$i` that is not whitespace or a comment.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function next(array $tokens, int $i): ?\PhpToken
    {
        return $tokens[self::nextIndex($tokens, $i)] ?? null;
    }

    /**
     * Index of the next token after `$i` that is not whitespace or a comment, or the token count.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function nextIndex(array $tokens, int $i): int
    {
        while (isset($tokens[++$i]) && $tokens[$i]->isIgnorable());

        return $i;
    }

    /**
     * Index of the previous token before `$i` that is not whitespace or a comment, or -1.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function previousIndex(array $tokens, int $i): int
    {
        while (isset($tokens[--$i]) && $tokens[$i]->isIgnorable());

        return $i;
    }

    /**
     * Index of the bracket closing the one opened at `$open`, or of the last token.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function closer(array $tokens, int $open): int
    {
        return self::$pairs[$open] ?? \count($tokens) - 1;
    }

    /**
     * Pairs of matching brackets, both ways: opener index => closer index and closer index => opener index.
     *
     * @param list<\PhpToken> $tokens
     * @return array<int, int>
     */
    private static function pairs(array $tokens): array
    {
        $pairs = [];
        $open = [];
        foreach ($tokens as $i => $t) {
            if (\in_array($t->text, self::OPENER, true)) {
                $open[] = $i;
            } elseif (($t->text === ')' || $t->text === ']' || $t->text === '}') && $open !== []) {
                $o = \array_pop($open);
                $pairs[$o] = $i;
                $pairs[$i] = $o;
            }
        }

        return $pairs;
    }

    /**
     * Line of the last token of the expression starting at `$i`: up to a `;` or `,` outside brackets, or
     * the bracket that encloses it.
     *
     * @param list<\PhpToken> $tokens
     * @param bool $colon Whether a `:` outside brackets ends the expression too: the first branch of a ternary.
     */
    private static function endLine(array $tokens, int $i, bool $colon = false): int
    {
        $last = $tokens[self::endIndex($tokens, $i, $colon)];

        return $last->line + \substr_count($last->text, "\n");
    }

    /**
     * Index of the last token of the expression starting at `$i`, as {@see endLine()} bounds it.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function endIndex(array $tokens, int $i, bool $colon = false): int
    {
        $end = $i;
        $depth = 0;
        for ($count = \count($tokens); $i < $count; $i++) {
            $t = $tokens[$i];
            if ($t->isIgnorable()) {
                continue;
            }
            if (\in_array($t->text, self::OPENER, true)) {
                $depth++;
            } elseif ($t->text === ')' || $t->text === ']' || $t->text === '}') {
                if (--$depth < 0) {
                    break;
                }
            } elseif ($depth === 0 && ($t->text === ';' || $t->text === ',' || $t->id === \T_CLOSE_TAG
                || ($colon && $t->text === ':'))
            ) {
                break;
            }
            $end = $i;
        }

        return $end;
    }

    /**
     * Whether the operand before the operator at `$i` is built of literals and constants alone, from the
     * start of its expression: PHP may fold it and drop what it decides.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function constantBefore(array $tokens, int $i): bool
    {
        # The walk stops at the open tag at the latest: it is neither a boundary nor foldable.
        $any = false;
        do {
            $t = $tokens[$i = self::previousIndex($tokens, $i)];
            if ($t->is([\T_RETURN, \T_DOUBLE_ARROW]) || \in_array($t->text, ['(', '=', ',', ';', '{', '[', '?', ':'], true)) {
                return $any;
            }
            $any = true;
        } while ($t->is([...self::FOLDABLE, \T_DOUBLE_COLON, \T_IS_EQUAL, \T_IS_NOT_EQUAL, \T_IS_IDENTICAL,
            \T_IS_NOT_IDENTICAL, \T_IS_SMALLER_OR_EQUAL, \T_IS_GREATER_OR_EQUAL, '<', '>', '!']));

        return false;
    }

    /**
     * Whether the token at `$i` starts an operation that compiles to an opcode on its own line.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function operates(array $tokens, int $i, ?\PhpToken $previous): bool
    {
        $t = $tokens[$i];
        $nextIndex = self::nextIndex($tokens, $i);
        $next = $tokens[$nextIndex] ?? null;

        # A closure is created where it starts; a named function declared in code is bound while compiling.
        if ($t->is([\T_FUNCTION, \T_FN])) {
            return $next?->text === '(' || ($next?->text === '&' && self::next($tokens, $nextIndex)?->text === '(');
        }

        # A `case` compares on its line, unless PHP resolves its label into a jump table.
        if ($t->id === \T_CASE) {
            return $next !== null && ($next->is([\T_LNUMBER, \T_DNUMBER, \T_CONSTANT_ENCAPSED_STRING, \T_VARIABLE])
                || \in_array(\strtolower($next->text), ['true', 'false', 'null'], true));
        }

        # A keyword that consumes a value is emitted once the value is computed.
        if ($t->is(self::CONSUMER)) {
            return self::endLine($tokens, $nextIndex) === $t->line;
        }

        if ($t->is(self::ACTION) || $t->text === '@') {
            return true;
        }

        # An assignment takes the line its target starts on. A compound one is emitted once its value is
        # computed, so a value spread over lines moves it.
        if ($t->text === '=') {
            return self::targetStartsOn($tokens, $i, $t->line);
        }
        if ($t->is(self::ASSIGNMENT)) {
            return self::targetStartsOn($tokens, $i, $t->line) && self::endLine($tokens, $nextIndex) === $t->line;
        }

        # A string that interpolates, or a shell command, is assembled where it starts, unless its first part
        # breaks the line.
        if ($t->text === '"' || $t->text === '`') {
            return !($next?->id === \T_ENCAPSED_AND_WHITESPACE && \str_contains($next->text, "\n"));
        }

        # A condition on one line puts its jump there; a longer one leaves it to the operations inside, and
        # a constant one may compile to no jump at all.
        if ($t->is([\T_IF, \T_ELSEIF, \T_WHILE, \T_FOREACH])) {
            $close = self::closer($tokens, $nextIndex);
            # An endless `while` jumps back to its condition line.
            if ($t->id === \T_WHILE && self::endless($tokens, $i)) {
                return $tokens[$close]->line === $t->line;
            }
            for ($k = $nextIndex + 1; $k < $close; $k++) {
                if ($tokens[$k]->is([\T_VARIABLE, \T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR]) || $tokens[$k]->text === '(') {
                    return $tokens[$close]->line === $t->line;
                }
            }

            return false;
        }

        # A unary operation is emitted after its operand; a literal operand folds.
        if ($t->is(self::CAST) || $t->text === '!' || $t->text === '~') {
            $operand = self::operandStart($tokens, $nextIndex);

            return $operand !== null && !$operand->is(self::FOLDABLE) && self::operandEndLine($tokens, $nextIndex) === $t->line;
        }

        # A branch of a ternary that fits on one line is assigned on its line.
        if (($t->text === '?' && $previous?->is(self::OPERAND_END)) || ($t->text === ':' && self::ternaryColon($tokens, $i))) {
            $branch = $next !== null && $next->line === $t->line && $next->text !== '[' && $next->id !== \T_ARRAY
                && self::endLine($tokens, $nextIndex, $t->text === '?') === $t->line;
            if ($branch || $t->text === ':') {
                return $branch;
            }
        }

        # A binary operation is emitted after its operands; two literal operands fold. A short-circuit
        # jump follows the left operand alone.
        if ($t->is(self::BINARY)) {
            if ($previous === null || $previous->line !== $t->line || !$previous->is(self::OPERAND_END)) {
                return false;
            }
            if ($previous->text === ')' || $previous->text === ']') {
                $open = self::opener(self::previousIndex($tokens, $i));
                if ($tokens[$open]->line !== $t->line) {
                    return false;
                }
            }
            $operand = self::operandStart($tokens, $nextIndex);
            if ($operand === null || $operand->line !== $t->line || ($previous->is(self::FOLDABLE) && $operand->is(self::FOLDABLE))) {
                return false;
            }

            return $t->is(self::SHORT_CIRCUIT) || self::operandEndLine($tokens, $nextIndex) === $t->line;
        }

        if (!$t->is([...self::CHAIN, \T_ISSET, \T_EMPTY, \T_UNSET]) || $t->is(self::NAME_PREFIX)) {
            return false;
        }

        if ($next?->text === '(') {
            # `isset()`, `empty()` and `unset()` take the line of their argument.
            if ($t->is([\T_ISSET, \T_EMPTY, \T_UNSET])) {
                return self::next($tokens, $nextIndex)?->line === $t->line;
            }
            $multiLine = $tokens[self::closer($tokens, $nextIndex)]->line !== $t->line;

            return !($multiLine && $t->is([\T_STRING, \T_NAME_FULLY_QUALIFIED]) && self::compiledFunction($t->text));
        }

        # A property fetch takes the line its object starts on.
        if ($previous?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR])) {
            return self::chainStartsOn($tokens, $i, $t->line);
        }

        # A static property; a class constant may fold.
        return $previous?->id === \T_DOUBLE_COLON && $t->id === \T_VARIABLE && self::chainStartsOn($tokens, $i, $t->line);
    }

    /**
     * Whether the `:` at `$i` separates the branches of a ternary.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function ternaryColon(array $tokens, int $i): bool
    {
        $depth = 0;
        while (($i = self::previousIndex($tokens, $i)) >= 0) {
            $t = $tokens[$i];
            if ($t->text === ')' || $t->text === ']' || $t->text === '}') {
                $depth++;
            } elseif (\in_array($t->text, self::OPENER, true)) {
                if (--$depth < 0) {
                    return false;
                }
            } elseif ($depth === 0) {
                if ($t->text === '?') {
                    return true;
                }
                if ($t->text === ';' || $t->text === ',' || $t->text === ':' || $t->is([\T_DOUBLE_ARROW, \T_CASE, \T_DEFAULT])) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * The first token of the operand starting at `$i`, past opening parentheses.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function operandStart(array $tokens, int $i): ?\PhpToken
    {
        while (($tokens[$i] ?? null)?->text === '(') {
            $i = self::nextIndex($tokens, $i);
        }

        return $tokens[$i] ?? null;
    }

    /**
     * Line the operand starting at `$i` ends on: a name, variable or literal with the fetches, calls and
     * brackets that follow it.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function operandEndLine(array $tokens, int $i): int
    {
        $line = $tokens[$i]->line;
        $prefix = true;
        while (isset($tokens[$i])) {
            $t = $tokens[$i];
            if (\in_array($t->text, self::OPENER, true)) {
                $i = self::closer($tokens, $i);
            } elseif ($t->text === '"') {
                while (isset($tokens[++$i]) && $tokens[$i]->text !== '"');
            } elseif ($t->id === \T_START_HEREDOC) {
                while (isset($tokens[++$i]) && $tokens[$i]->id !== \T_END_HEREDOC);
            } elseif (!$t->is([...self::CHAIN, ...self::FOLDABLE, \T_NEW, \T_CLONE, \T_ISSET, \T_EMPTY, \T_ARRAY])
                && !($prefix && ($t->is(self::CAST) || \in_array($t->text, ['!', '~', '@', '-', '+'], true)))
            ) {
                break;
            }
            $prefix = $t->is(self::CAST) || \in_array($t->text, ['!', '~', '@', '-', '+'], true);
            # A string left open runs to the end of the source.
            $i = \min($i, \count($tokens) - 1);
            $line = $tokens[$i]->line + \substr_count($tokens[$i]->text, "\n");
            $i = self::nextIndex($tokens, $i);
        }

        return $line;
    }

    /**
     * Index of the bracket opening the one closed at `$close`, or 0.
     */
    private static function opener(int $close): int
    {
        return self::$pairs[$close] ?? 0;
    }

    /**
     * Whether the target written by the assignment operator at `$i` starts on `$line`: a variable with the
     * fetches and indexes that follow it.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function targetStartsOn(array $tokens, int $i, int $line): bool
    {
        for ($k = self::previousIndex($tokens, $i); isset($tokens[$k]); $k = self::previousIndex($tokens, $k)) {
            $t = $tokens[$k];
            if ($t->text === '}') {
                # Only a name built by an expression, `$o->{$name}`, is part of the target; a block ends before it.
                $open = self::opener($k);
                $before = $tokens[self::previousIndex($tokens, $open)] ?? null;
                if (!$before?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON]) && $before?->text !== '$') {
                    break;
                }
                $k = $open;
            } elseif ($t->text === ')' || $t->text === ']') {
                $k = self::opener($k);
            } elseif (!$t->is(self::CHAIN) && $t->text !== '$') {
                break;
            }
            if ($tokens[$k]->line !== $line) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the chain of fetches ending at `$i` (`$a->b::$c`) is a simple one that starts on `$line`.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function chainStartsOn(array $tokens, int $i, int $line): bool
    {
        while ($i >= 0 && $tokens[$i]->is(self::CHAIN)) {
            if ($tokens[$i]->line !== $line) {
                return false;
            }
            $i = self::previousIndex($tokens, $i);
        }

        # An object built by a call or an index may start on an earlier line.
        return $i < 0 || !\in_array($tokens[$i]->text, [')', ']', '}'], true);
    }

    /**
     * Whether the name is a function PHP compiles into a dedicated opcode.
     */
    private static function compiledFunction(string $name): bool
    {
        $name = \strtolower(\ltrim($name, '\\'));

        return \in_array($name, self::COMPILED_FUNCTIONS, true);
    }

    /**
     * Whether PHP may decide the condition of the `if` or `elseif` at `$i` while compiling: it is built of
     * literals and constants alone, or a logical operator in it follows such an operand.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function foldableCondition(array $tokens, int $i): bool
    {
        $open = self::nextIndex($tokens, $i);
        $close = self::closer($tokens, $open);
        $constant = true;
        for ($k = self::nextIndex($tokens, $open); $k < $close; $k = self::nextIndex($tokens, $k)) {
            $t = $tokens[$k];
            if ($t->is([\T_BOOLEAN_AND, \T_BOOLEAN_OR, \T_LOGICAL_AND, \T_LOGICAL_OR]) && self::constantBefore($tokens, $k)) {
                return true;
            }
            $t->is([...self::FOLDABLE, \T_DOUBLE_COLON, \T_IS_EQUAL, \T_IS_NOT_EQUAL, \T_IS_IDENTICAL,
                \T_IS_NOT_IDENTICAL, \T_IS_SMALLER_OR_EQUAL, \T_IS_GREATER_OR_EQUAL, \T_BOOLEAN_AND, \T_BOOLEAN_OR,
                '<', '>', '!', '(', ')']) or $constant = false;
            # A name followed by `(` is a call.
            $t->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED]) && self::next($tokens, $k)?->text === '('
                and $constant = false;
        }

        return $constant;
    }

    /**
     * Index of the last token of the `if` chain whose `if` or `elseif` is at `$i`: its branches, the
     * `else` included. A chain in the alternative syntax ends with its condition.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function chainEnd(array $tokens, int $i): int
    {
        $close = self::closer($tokens, self::nextIndex($tokens, $i));
        $body = self::nextIndex($tokens, $close);
        if (($tokens[$body] ?? null)?->text === ':') {
            return $close;
        }
        $end = self::branchEnd($tokens, $body);
        $after = self::nextIndex($tokens, $end);
        if (($tokens[$after] ?? null)?->id === \T_ELSEIF) {
            return self::chainEnd($tokens, $after);
        }
        if (($tokens[$after] ?? null)?->id !== \T_ELSE) {
            return $end;
        }
        $branch = self::nextIndex($tokens, $after);

        return $tokens[$branch]->id === \T_IF ? self::chainEnd($tokens, $branch) : self::branchEnd($tokens, $branch);
    }

    /**
     * Index of the last token of the branch starting at `$i`: a block or a single statement.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function branchEnd(array $tokens, int $i): int
    {
        return ($tokens[$i] ?? null)?->text === '{'
            ? self::closer($tokens, $i)
            : self::nextIndex($tokens, self::endIndex($tokens, $i));
    }

    /**
     * Whether the `while` or `for` at `$i` has a condition that is always true: `true`, `1` or none.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function endless(array $tokens, int $i): bool
    {
        $open = self::nextIndex($tokens, $i);
        $close = self::closer($tokens, $open);
        $parts = [[]];
        $depth = 0;
        for ($k = $open + 1; $k < $close; $k++) {
            $t = $tokens[$k];
            if ($t->isIgnorable()) {
                continue;
            }
            \in_array($t->text, self::OPENER, true) and $depth++;
            \in_array($t->text, [')', ']', '}'], true) and $depth--;
            if ($depth === 0 && $t->text === ';') {
                $parts[] = [];
                continue;
            }
            $parts[\array_key_last($parts)][] = \strtolower($t->text);
        }
        $condition = $tokens[$i]->id === \T_FOR ? ($parts[1] ?? null) : $parts[0];

        return $condition === [] || $condition === ['true'] || $condition === ['1'];
    }

    /**
     * Whether the token at `$i` names a call of `assert()`.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function callsAssert(array $tokens, int $i): bool
    {
        $t = $tokens[$i];

        return $t->is([\T_STRING, \T_NAME_FULLY_QUALIFIED])
            && \strcasecmp(\ltrim($t->text, '\\'), 'assert') === 0
            && self::next($tokens, $i)?->text === '('
            && !($tokens[self::previousIndex($tokens, $i)] ?? null)?->is([...self::NAME_PREFIX, \T_NEW]);
    }

    /**
     * Whether PHP knows the class before `::` while compiling, so its constant folds into the value.
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
     * Marks the innermost open array as built at runtime.
     *
     * @param list<array{type: '('|'[', line: int, const: bool, weak: array<int, true>, call: bool, shape: list<int>, dynamic: bool, from: int|null, to: int|null}> $brackets
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
