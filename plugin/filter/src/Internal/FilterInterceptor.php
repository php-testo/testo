<?php

declare(strict_types=1);

namespace Testo\Filter\Internal;

use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Filter;
use Testo\Filter\DataPointer;
use Testo\Filter\Group;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Three-stage interceptor for filtering test execution by name patterns and data provider indices.
 *
 * Stage 1 (FileLocatorInterceptor): Pre-filters test files before loading for reflection analysis.
 * Stage 2 (CaseLocatorInterceptor): Filters test cases and individual tests before execution.
 * Stage 3 (TestRunInterceptor): Injects {@see \Testo\Filter\DataPointer} to tests metadata for data provider filtering.
 *
 * Supports three filter formats with optional DataProvider indices:
 * - FQN: `Namespace\ClassName:0:1` - provider 0, dataset 1
 * - Method: `ClassName::methodName:0` - provider 0, all datasets
 * - Fragment: `methodName:1:5` - provider 1, dataset 5
 *
 * DataProvider indices (optional):
 * - Format: `name:providerIndex:datasetIndex`
 * - Indices are 0-based integers, independent of dataset labels
 * - datasetIndex is optional (omit to run all datasets from provider)
 * - Examples: `UserTest::testLogin:0`, `testAuth:1:3`, `UserTest:0`
 *
 * Filtering logic (OR across all patterns):
 * - If test case name (class name) matches: entire case passes with all tests
 * - If test case name doesn't match: filter individual methods/functions
 *   - If any methods/functions match: case passes with only matched tests
 *   - If no methods/functions match: case is skipped entirely
 *
 * @internal
 * @psalm-internal Testo\Filter
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_FILTER, onConflict: ConflictPolicy::First)]
final class FilterInterceptor implements FileLocatorInterceptor, CaseLocatorInterceptor, TestRunInterceptor
{
    /** @var bool True if filtering is disabled entirely (no name and no group filters provided) */
    private readonly bool $skip;

    /** @var bool True if no name filters were provided (group filters may still apply) */
    private readonly bool $nameSkip;

    /** @var bool True if no group filters were provided */
    private readonly bool $groupSkip;

    /**
     * Group names to include. A test passes when its group set intersects this list (OR logic).
     *
     * @var list<non-empty-string>
     */
    private readonly array $groups;

    /**
     * Group names to exclude. A test is dropped when its group set intersects this list.
     *
     * @var list<non-empty-string>
     */
    private readonly array $excludeGroups;

    /**
     * Fully qualified names to filter by (without provider/dataset indices).
     * Example: `Namespace\ClassName` or `Namespace\functionName`.
     *
     * Indices are extracted during parsing and stored separately in DataPointer.
     *
     * @var list<array{non-empty-string, null|\Testo\Filter\DataPointer}> Tuple of [cleanName, pointer]
     */
    private readonly array $fqn;

    /**
     * Method names to filter by (without provider/dataset indices).
     * Example: `ClassName::methodName`.
     *
     * Indices are extracted during parsing and stored separately in DataPointer.
     *
     * @var list<array{non-empty-string, non-empty-string, null|\Testo\Filter\DataPointer}> Tuple of [className, methodName, pointer]
     */
    private readonly array $method;

    /**
     * Name fragments to filter by (without provider/dataset indices).
     * Example: `methodName`, `shortFunctionName`, or `ShortClassName`.
     *
     * Indices are extracted during parsing and stored separately in DataPointer.
     *
     * @var list<array{non-empty-string, null|\Testo\Filter\DataPointer}> Tuple of [fragment, pointer]
     */
    private readonly array $fragment;

    /**
     * Mapping of test reflections to their DataPointer (if specified in filter).
     *
     * Populated in Stage 2 (locateTestCases) when matching tests.
     * Used in Stage 3 (runTest) to inject pointer into TestInfo attributes.
     *
     * @var \SplObjectStorage<\ReflectionFunctionAbstract, null|DataPointer>
     */
    private \SplObjectStorage $pointers;

    public function __construct(
        Filter $filter,
    ) {
        $fqn = $method = $fragment = [];
        foreach ($filter->names as $name) {
            $f = \explode('::', \ltrim($name, '\\'));
            if (\str_contains($name, '::')) {
                $pointer = self::extractDataPointer($f[1]);

                $method[] = [
                    $f[0],
                    $f[1],
                    $pointer,
                ];
            } elseif (\str_contains($name, '\\')) {
                $f = \trim($name, '\\');
                $pointer = self::extractDataPointer($f);
                $fqn[] = [$f, $pointer];
            } else {
                $pointer = self::extractDataPointer($name);
                $fragment[] = [$name, $pointer];
            }
        }

        $this->nameSkip = $fqn === [] && $method === [] && $fragment === [];
        $this->groupSkip = $filter->groups === [] && $filter->excludeGroups === [];
        $this->skip = $this->nameSkip && $this->groupSkip;
        $this->fqn = $fqn;
        $this->method = $method;
        $this->fragment = $fragment;
        $this->groups = $filter->groups;
        $this->excludeGroups = $filter->excludeGroups;
        $this->skip or $this->pointers = new \SplObjectStorage();
    }

    /**
     * Stage 1: Filter test files before loading for reflection analysis.
     *
     * Performs quick pre-filtering based on tokenized file data to skip files
     * that don't contain any matching classes, methods, or functions.
     *
     * @param TokenizedFile $file Tokenized file with class/function/method names
     * @param callable(TokenizedFile): (null|bool) $next Next interceptor in the chain
     *
     * @return bool|null True to include file, false to skip, null for passthrough
     */
    #[\Override]
    public function locateFile(TokenizedFile $file, callable $next): ?bool
    {
        # Group filters require reflection (Stage 2); tokens carry no attributes,
        # so when there are no name filters we cannot pre-filter files here.
        return match (true) {
            $this->nameSkip,
            $this->matchFile($file) => $next($file),
            default => false,
        };
    }

    /**
     * Stage 2: Filter test cases and methods after reflection analysis.
     *
     * Filtering is the AND of two independent passes (a test survives only if it passes both):
     *
     * Name pass:
     * - If class name matches: the whole case is eligible (all its tests).
     * - Otherwise: only methods/functions whose name matches are eligible.
     * - If no name filters are provided, every test is eligible.
     *
     * Group pass ({@see Group}):
     * - The group set of a test is the union of its class-level and method/function-level groups.
     * - With include groups: a test passes only if its group set intersects them (OR logic).
     * - With exclude groups: a test is dropped if its group set intersects them (takes precedence).
     *
     * @param FileDefinitions $file File with test case definitions
     * @param callable(FileDefinitions): CaseDefinitions $next Next interceptor in the chain
     *
     * @return CaseDefinitions Filtered test case definitions
     */
    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        if ($this->skip) {
            return $next($file);
        }

        $definitions = $next($file);

        $result = [];
        foreach ($definitions->getCases() as $case) {
            # Resolve the case (class) groups once. Skipped entirely when no group filters are set.
            $caseGroups = $this->groupSkip || $case->reflection === null
                ? []
                : self::groupNamesOf($case->reflection);

            # Class-level group short-circuit, evaluated before any per-method work: case groups
            # are inherited by every test, so a case excluded by group is dropped without name matching.
            if ($this->excludeGroups !== [] && \array_intersect($caseGroups, $this->excludeGroups) !== []) {
                continue;
            }

            $tests = $this->matchTestsByName($case);
            if ($tests === []) {
                continue;
            }

            $tests = $this->filterTestsByGroup($caseGroups, $tests);
            $tests === [] or $result[] = $case->with(tests: TestDefinitions::fromArray(...$tests));
        }

        return CaseDefinitions::fromArray(...$result);
    }

    /**
     * Name pass: select the tests of a case that match the configured name filters.
     *
     * Also records {@see DataPointer}s for matched tests so Stage 3 can inject them.
     *
     *
     * @return array<string, TestDefinition> Matched tests keyed by name
     */
    private function matchTestsByName(CaseDefinition $case): array
    {
        # No name filters: every test is eligible.
        if ($this->nameSkip) {
            return $case->tests->getTests();
        }

        $methods = [];

        if ($case->reflection !== null) {
            $className = $case->reflection->getName();

            # Match class name: the whole case is eligible.
            foreach ([...$this->fqn, ...$this->fragment] as [$name, $_]) {
                if (self::has($name, $className)) {
                    return $case->tests->getTests();
                }
            }

            # Match methods by class::method.
            foreach ($this->method as [$filterClass, $filterMethod, $pointer]) {
                if (!self::has($filterClass, $className)) {
                    continue;
                }

                foreach ($case->tests->getTests() as $name => $test) {
                    if ($filterMethod === $test->reflection->getShortName()) {
                        $methods[$name] = $test;
                        $this->pointers[$test->reflection] = $pointer;
                    }
                }
            }
        }

        # Match by function name (or FQN).
        foreach ($case->tests->getTests() as $name => $test) {
            foreach ([...$this->fqn, ...$this->fragment] as [$f, $pointer]) {
                if (self::has($f, $test->reflection->getName())) {
                    $methods[$name] = $test;
                    $this->pointers[$test->reflection] = $pointer;
                    continue 2;
                }
            }
        }

        return $methods;
    }

    /**
     * Group pass: keep only tests whose group set satisfies the include/exclude filters.
     *
     * The case-level exclude short-circuit is handled earlier in {@see self::locateTestCases()};
     * here we only resolve include at the class level and apply per-method exclude/include.
     *
     * @param list<non-empty-string> $caseGroups Groups inherited from the test case (class).
     * @param array<string, TestDefinition> $tests
     *
     * @return array<string, TestDefinition> Surviving tests keyed by name
     */
    private function filterTestsByGroup(array $caseGroups, array $tests): array
    {
        if ($this->groupSkip) {
            return $tests;
        }

        # If the class already satisfies the include filter, every test inherits that match.
        $classIncluded = $this->groups === [] || \array_intersect($caseGroups, $this->groups) !== [];

        # Class is included and nothing can be excluded per method → keep all tests as-is,
        # without loading any method-level groups.
        if ($classIncluded && $this->excludeGroups === []) {
            return $tests;
        }

        $result = [];
        foreach ($tests as $name => $test) {
            # Only the method's own groups are needed here: the case is already known not to be
            # excluded (checked in locateTestCases) and not to match include (otherwise
            # $classIncluded would be true), so merging $caseGroups in cannot change either test.
            $groups = self::groupNamesOf($test->reflection);

            # Exclude takes precedence.
            if ($this->excludeGroups !== [] && \array_intersect($groups, $this->excludeGroups) !== []) {
                continue;
            }

            # Include filter: skip when the class already matched it.
            if (!$classIncluded && \array_intersect($groups, $this->groups) === []) {
                continue;
            }

            $result[$name] = $test;
        }

        return $result;
    }

    /**
     * Collect all group names declared on a class, method, or function via {@see Group}.
     *
     * More than one {@see Group} may be returned because parent classes, traits, and method
     * prototypes are traversed — not because the attribute repeats on a single declaration.
     *
     * @return list<non-empty-string>
     */
    private static function groupNamesOf(\ReflectionClass|\ReflectionFunctionAbstract $reflection): array
    {
        $attributes = $reflection instanceof \ReflectionClass
            ? Reflection::fetchClassAttributes($reflection, attributeClass: Group::class)
            : Reflection::fetchFunctionAttributes($reflection, attributeClass: Group::class);

        $names = [];
        foreach ($attributes as $attribute) {
            foreach ($attribute->newInstance()->names as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Stage 3: Inject data pointers to individual tests before execution.
     *
     * The {@see \Testo\Filter\DataPointer} attribute can be used by data providers or test runners to
     * identify which dataset of which provider is being referred to.
     *
     * @param TestInfo $info Test information
     * @param callable(TestInfo): TestResult $next Next interceptor in the chain
     *
     * @return TestResult Test execution result
     */
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        if ($this->skip) {
            return $next($info);
        }

        $pointer = $this->pointers[$info->testDefinition->reflection] ?? null;

        return $pointer === null
            ? $next($info)
            : $next($info->withAttribute(DataPointer::class, $pointer));
    }

    /**
     * @return bool True if the needle is found as a whole word in the haystack, false otherwise.
     */
    private static function has(string $needle, string $haystack): bool
    {
        return \preg_match('/\\b' . \preg_quote($needle, '/') . '\\b$/', $haystack) === 1;
    }

    /**
     * Extract {@see \Testo\Filter\DataPointer} from target string and remove indices from target.
     *
     * Parses format: `name:providerIndex:datasetIndex` where datasetIndex is optional.
     * Modifies $target by reference, removing `:providerIndex:datasetIndex` parts.
     *
     * Examples:
     * - "testMethod:0:1" -> target becomes "testMethod", returns DataPointer(0, 1)
     * - "testMethod:2" -> target becomes "testMethod", returns DataPointer(2, null)
     * - "testMethod" -> target unchanged, returns null
     *
     * @param non-empty-string &$target Name with optional indices. Indices removed after parsing.
     * @return null|\Testo\Filter\DataPointer DataPointer if indices present, null otherwise
     */
    private static function extractDataPointer(string &$target): ?DataPointer
    {
        # Expecting that the target must not contain '::'
        $parts = \explode(':', $target);
        if (\count($parts) === 1) {
            return null;
        }

        $target = $parts[0];
        return new DataPointer((int) $parts[1], isset($parts[2]) ? (int) $parts[2] : null);
    }

    /**
     * Check if tokenized file contains any matching classes, functions, or methods.
     *
     * Performs quick matching against tokenized file data without loading full reflections.
     * Checks functions, classes, and methods in sequence, returning true on first match.
     *
     * Token data is only complete for symbols physically declared in the file. A method inherited
     * from a parent in another file leaves no token here — only the subclass declaration does. So
     * matching is anchored on the class wherever possible:
     * - `Class::method`: matched by the class part alone (the method may be inherited); precise,
     *   since the class is always declared where it is tokenized.
     * - bare fragment (`method`): nothing in the file anchors an inherited method name, so when the
     *   file declares a subclass we keep it and let Stage 2 (reflection) decide. See {@see declaresSubclass()}.
     *
     * @param TokenizedFile $file Tokenized file with extracted names
     *
     * @return bool True if any name matches, false otherwise
     */
    private function matchFile(TokenizedFile $file): bool
    {
        # Match functions (by FQN or fragment). Functions are declared where tokenized, so reliable.
        foreach ($file->getFunctions() as $fqn) {
            foreach ([...$this->fqn, ...$this->fragment] as [$name, $_]) {
                if (self::has($name, $fqn)) {
                    return true;
                }
            }
        }

        # Match classes by name (FQN or fragment), and by the class part of `Class::method` filters.
        # The class part is enough: its test method may be inherited from a parent in another file,
        # but the class itself is always declared (and tokenized) here.
        foreach ($file->getClasses() as $class) {
            foreach ([...$this->fqn, ...$this->fragment] as [$name, $_]) {
                if (self::has($name, $class)) {
                    return true;
                }
            }

            foreach ($this->method as [$className]) {
                if (self::has($className, $class)) {
                    return true;
                }
            }
        }

        # Match by fragment against methods declared in this file. (The `Class::method` form is
        # already covered by the class anchor above.)
        foreach ($file->getMethodsFQN() as $fqn) {
            foreach ($this->fragment as [$name, $_]) {
                if (self::has($name, $fqn)) {
                    return true;
                }
            }
        }

        # A bare fragment may name a method inherited from a parent whose token lives in another file,
        # leaving nothing here to match. When the file declares a subclass we cannot rule that out
        # from tokens, so keep it for Stage 2 reflection rather than risk a false negative.
        if ($this->fragment !== [] && self::declaresSubclass($file)) {
            return true;
        }

        return false;
    }

    /**
     * Whether the file declares a named class that extends a parent — i.e. it may expose methods
     * inherited from another file that the token pre-filter cannot see.
     *
     * Detected via the `extends` keyword token rather than reflection to keep Stage 1 cheap; an
     * over-match (e.g. a parent with no matching test) is harmless — Stage 2 filters it out.
     *
     * Only `extends` of a named class counts: a named declaration is `class <Name> extends`, so the
     * tokens right before `extends` are the name (T_STRING) and then the `class` keyword. An
     * anonymous class (`new class extends ...`, `new class (...) extends ...`) has no name there and
     * can never be a discoverable test case, so it is ignored. `interface ... extends` is likewise
     * skipped — the keyword before the name is not `class`.
     */
    private static function declaresSubclass(TokenizedFile $file): bool
    {
        $tokens = $file->tokens;

        foreach ($tokens as $i => $token) {
            if (!$token->is(\T_EXTENDS)) {
                continue;
            }

            # Token before `extends`, skipping whitespace: the class name for a named declaration.
            $name = $i - 1;
            while ($name >= 0 && $tokens[$name]->is(\T_WHITESPACE)) {
                --$name;
            }
            if ($name < 0 || !$tokens[$name]->is(\T_STRING)) {
                continue;
            }

            # Token before the name, skipping whitespace: the `class` keyword for a class declaration.
            $keyword = $name - 1;
            while ($keyword >= 0 && $tokens[$keyword]->is(\T_WHITESPACE)) {
                --$keyword;
            }
            if ($keyword >= 0 && $tokens[$keyword]->is(\T_CLASS)) {
                return true;
            }
        }

        return false;
    }
}
