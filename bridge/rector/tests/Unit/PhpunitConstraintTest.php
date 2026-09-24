<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Testo\Assert;
use Testo\Bridge\Rector\Internal\PhpunitConstraint;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * The predicate generated for a PHPUnit constraint must give the verdict PHPUnit's own `evaluate()`
 * gives. The expected verdicts in {@see self::verdicts()} were taken from PHPUnit itself, including the
 * edge values each constraint guards against (a non-string for the string constraints, a boolean for
 * the equality ones).
 */
#[Test]
#[Covers(PhpunitConstraint::class)]
final class PhpunitConstraintTest
{
    #[DataProvider('verdicts')]
    public function predicateGivesPhpunitsVerdict(string $constraint, mixed $value, bool $expected): void
    {
        $builder = new PhpunitConstraint('value');
        $predicate = $builder->predicate(self::parse($constraint));
        Assert::notNull($predicate);

        $closure = eval('return ' . (new Standard())->prettyPrintExpr($builder->closure($predicate)) . ';');

        Assert::same((bool) $closure($value), $expected);
    }

    #[DataSet(['self::stringContains("x", $ignoreCase)'], 'computed case flag')]
    #[DataSet(['self::stringContains("x", false, true)'], 'line-ending flag')]
    #[DataSet(['self::isType("unknown")'], 'unknown type name')]
    #[DataSet(['self::isType($type)'], 'computed type name')]
    #[DataSet(['self::containsOnly($type)'], 'computed item type')]
    #[DataSet(['self::objectEquals($expected, $method)'], 'computed comparison method')]
    #[DataSet(['self::equalTo(value: 5)'], 'named argument')]
    #[DataSet(['self::matches("%s")'], 'format description')]
    #[DataSet(['self::logicalOr(self::isType($type), 1)'], 'unmappable operand')]
    public function constraintWithoutFaithfulFormHasNoPredicate(string $constraint): void
    {
        Assert::null((new PhpunitConstraint('value'))->predicate(self::parse($constraint)));
    }

    public static function verdicts(): iterable
    {
        yield 'equalTo(5) on 5' => ['self::equalTo(5)', 5, true];
        yield 'equalTo(5) on \'5\'' => ['self::equalTo(5)', '5', true];
        yield 'equalTo(5) on 9' => ['self::equalTo(5)', 9, false];
        yield 'equalTo(5) on true' => ['self::equalTo(5)', true, true];
        yield 'equalTo(5) on null' => ['self::equalTo(5)', null, false];
        yield 'identicalTo(5) on 5' => ['self::identicalTo(5)', 5, true];
        yield 'identicalTo(5) on \'5\'' => ['self::identicalTo(5)', '5', false];
        yield 'identicalTo(5) on 5.05' => ['self::identicalTo(5)', 5.05, false];
        yield 'isNull() on null' => ['self::isNull()', null, true];
        yield 'isNull() on false' => ['self::isNull()', false, false];
        yield 'isNull() on \'\'' => ['self::isNull()', '', false];
        yield 'greaterThan(5) on 9' => ['self::greaterThan(5)', 9, true];
        yield 'greaterThan(5) on 5' => ['self::greaterThan(5)', 5, false];
        yield 'greaterThan(5) on \'9\'' => ['self::greaterThan(5)', '9', true];
        yield 'greaterThan(5) on null' => ['self::greaterThan(5)', null, false];
        yield 'lessThanOrEqual(5) on 5' => ['self::lessThanOrEqual(5)', 5, true];
        yield 'lessThanOrEqual(5) on 9' => ['self::lessThanOrEqual(5)', 9, false];
        yield 'lessThanOrEqual(5) on 5.05' => ['self::lessThanOrEqual(5)', 5.05, false];
        yield 'equalToWithDelta(5.0, 0.1) on 5' => ['self::equalToWithDelta(5.0, 0.1)', 5, true];
        yield 'equalToWithDelta(5.0, 0.1) on 5.05' => ['self::equalToWithDelta(5.0, 0.1)', 5.05, true];
        yield 'equalToWithDelta(5.0, 0.1) on 9' => ['self::equalToWithDelta(5.0, 0.1)', 9, false];
        yield 'equalToWithDelta(5.0, 0.1) on true' => ['self::equalToWithDelta(5.0, 0.1)', true, true];
        yield 'equalToWithDelta(5.0, 0.1) on \'abc\'' => ['self::equalToWithDelta(5.0, 0.1)', 'abc', false];
        yield 'equalToWithDelta(5.0, 0.1) on []' => ['self::equalToWithDelta(5.0, 0.1)', [], false];
        yield 'equalToIgnoringCase(\'abc\') on \'ABC\'' => ['self::equalToIgnoringCase(\'abc\')', 'ABC', true];
        yield 'equalToIgnoringCase(\'abc\') on \'axz\'' => ['self::equalToIgnoringCase(\'abc\')', 'axz', false];
        yield 'equalToIgnoringCase(\'abc\') on true' => ['self::equalToIgnoringCase(\'abc\')', true, true];
        yield 'equalToIgnoringCase(\'abc\') on null' => ['self::equalToIgnoringCase(\'abc\')', null, false];
        yield 'equalToIgnoringCase(\'abc\') on []' => ['self::equalToIgnoringCase(\'abc\')', [], false];
        yield 'equalToCanonicalizing([1, 2]) on [2, 1]' => ['self::equalToCanonicalizing([1, 2])', [2, 1], true];
        yield 'equalToCanonicalizing([1, 2]) on [1, 2]' => ['self::equalToCanonicalizing([1, 2])', [1, 2], true];
        yield 'equalToCanonicalizing([1, 2]) on [\'s\', \'t\']' => ['self::equalToCanonicalizing([1, 2])', ['s', 't'], false];
        yield 'equalToCanonicalizing([1, 2]) on true' => ['self::equalToCanonicalizing([1, 2])', true, false];
        yield 'equalToCanonicalizing([1, 2]) on 5' => ['self::equalToCanonicalizing([1, 2])', 5, false];
        yield 'isEmpty() on []' => ['self::isEmpty()', [], true];
        yield 'isEmpty() on \'\'' => ['self::isEmpty()', '', true];
        yield 'isEmpty() on \'0\'' => ['self::isEmpty()', '0', true];
        yield 'isEmpty() on new \\ArrayObject([])' => ['self::isEmpty()', new \ArrayObject([]), true];
        yield 'isEmpty() on new \\ArrayObject([\'k\' => 1])' => ['self::isEmpty()', new \ArrayObject(['k' => 1]), false];
        yield 'isEmpty() on [1, 2]' => ['self::isEmpty()', [1, 2], false];
        yield 'isList() on [1, 2]' => ['self::isList()', [1, 2], true];
        yield 'isList() on [\'k\' => 1]' => ['self::isList()', ['k' => 1], false];
        yield 'isList() on \'abc\'' => ['self::isList()', 'abc', false];
        yield 'isJson() on \'{"a":1}\'' => ['self::isJson()', '{"a":1}', true];
        yield 'isJson() on \'null\'' => ['self::isJson()', 'null', true];
        yield 'isJson() on \'{bad\'' => ['self::isJson()', '{bad', false];
        yield 'isJson() on \'\'' => ['self::isJson()', '', false];
        yield 'isJson() on 5' => ['self::isJson()', 5, false];
        yield 'countOf(2) on [1, 2]' => ['self::countOf(2)', [1, 2], true];
        yield 'countOf(2) on []' => ['self::countOf(2)', [], false];
        yield 'countOf(2) on \'ab\'' => ['self::countOf(2)', 'ab', false];
        yield 'countOf(2) on new \\ArrayObject([\'k\' => 1])' => ['self::countOf(2)', new \ArrayObject(['k' => 1]), false];
        yield 'stringContains(\'x\') on \'axz\'' => ['self::stringContains(\'x\')', 'axz', true];
        yield 'stringContains(\'x\') on \'abc\'' => ['self::stringContains(\'x\')', 'abc', false];
        yield 'stringContains(\'x\') on []' => ['self::stringContains(\'x\')', [], false];
        yield 'stringContains(\'x\') on 5' => ['self::stringContains(\'x\')', 5, false];
        yield 'stringContains(\'X\', true) on \'axz\'' => ['self::stringContains(\'X\', true)', 'axz', true];
        yield 'stringContains(\'X\', true) on \'abc\'' => ['self::stringContains(\'X\', true)', 'abc', false];
        yield 'stringContains(\'X\', true) on null' => ['self::stringContains(\'X\', true)', null, false];
        yield 'stringContains(\'\') on \'abc\'' => ['self::stringContains(\'\')', 'abc', true];
        yield 'stringContains(\'\') on 5' => ['self::stringContains(\'\')', 5, true];
        yield 'stringContains(\'\') on []' => ['self::stringContains(\'\')', [], true];
        yield 'stringStartsWith(\'a\') on \'abc\'' => ['self::stringStartsWith(\'a\')', 'abc', true];
        yield 'stringStartsWith(\'a\') on \'ABC\'' => ['self::stringStartsWith(\'a\')', 'ABC', false];
        yield 'stringStartsWith(\'a\') on []' => ['self::stringStartsWith(\'a\')', [], false];
        yield 'stringEndsWith(\'z\') on \'axz\'' => ['self::stringEndsWith(\'z\')', 'axz', true];
        yield 'stringEndsWith(\'z\') on \'abc\'' => ['self::stringEndsWith(\'z\')', 'abc', false];
        yield 'stringEndsWith(\'z\') on null' => ['self::stringEndsWith(\'z\')', null, false];
        yield 'matchesRegularExpression(\'/^a/\') on \'abc\'' => ['self::matchesRegularExpression(\'/^a/\')', 'abc', true];
        yield 'matchesRegularExpression(\'/^a/\') on \'ABC\'' => ['self::matchesRegularExpression(\'/^a/\')', 'ABC', false];
        yield 'matchesRegularExpression(\'/^a/\') on [1, 2]' => ['self::matchesRegularExpression(\'/^a/\')', [1, 2], false];
        yield 'arrayHasKey(\'k\') on [\'k\' => 1]' => ['self::arrayHasKey(\'k\')', ['k' => 1], true];
        yield 'arrayHasKey(\'k\') on [1, 2]' => ['self::arrayHasKey(\'k\')', [1, 2], false];
        yield 'arrayHasKey(\'k\') on new \\ArrayObject([\'k\' => 1])' => ['self::arrayHasKey(\'k\')', new \ArrayObject(['k' => 1]), true];
        yield 'arrayHasKey(\'k\') on new \\ArrayObject([])' => ['self::arrayHasKey(\'k\')', new \ArrayObject([]), false];
        yield 'arrayHasKey(\'k\') on \'k\'' => ['self::arrayHasKey(\'k\')', 'k', false];
        yield 'containsEqual(\'1\') on [1, 2]' => ['self::containsEqual(\'1\')', [1, 2], true];
        yield 'containsEqual(\'1\') on [\'s\', \'t\']' => ['self::containsEqual(\'1\')', ['s', 't'], false];
        yield 'containsEqual(\'1\') on \'1\'' => ['self::containsEqual(\'1\')', '1', false];
        yield 'containsIdentical(1) on [1, 2]' => ['self::containsIdentical(1)', [1, 2], true];
        yield 'containsIdentical(1) on [\'1\']' => ['self::containsIdentical(1)', ['1'], false];
        yield 'containsIdentical(1) on new \\ArrayObject([1])' => ['self::containsIdentical(1)', new \ArrayObject([1]), true];
        yield 'containsOnlyString() on [\'s\', \'t\']' => ['self::containsOnlyString()', ['s', 't'], true];
        yield 'containsOnlyString() on [1, 2]' => ['self::containsOnlyString()', [1, 2], false];
        yield 'containsOnlyString() on []' => ['self::containsOnlyString()', [], true];
        yield 'containsOnlyString() on \'s\'' => ['self::containsOnlyString()', 's', false];
        yield 'containsOnlyInstancesOf(\\ArrayObject::class) on [new \\ArrayObject()]' => ['self::containsOnlyInstancesOf(\\ArrayObject::class)', [new \ArrayObject()], true];
        yield 'containsOnlyInstancesOf(\\ArrayObject::class) on [new \\stdClass()]' => ['self::containsOnlyInstancesOf(\\ArrayObject::class)', [new \stdClass()], false];
        yield 'containsOnlyInstancesOf(\\ArrayObject::class) on []' => ['self::containsOnlyInstancesOf(\\ArrayObject::class)', [], true];
        yield 'isInstanceOf(\\ArrayObject::class) on new \\ArrayObject([])' => ['self::isInstanceOf(\\ArrayObject::class)', new \ArrayObject([]), true];
        yield 'isInstanceOf(\\ArrayObject::class) on new \\stdClass()' => ['self::isInstanceOf(\\ArrayObject::class)', new \stdClass(), false];
        yield 'isInstanceOf(\\ArrayObject::class) on \'ArrayObject\'' => ['self::isInstanceOf(\\ArrayObject::class)', 'ArrayObject', false];
        yield 'isInt() on 5' => ['self::isInt()', 5, true];
        yield 'isInt() on \'5\'' => ['self::isInt()', '5', false];
        yield 'isInt() on 5.05' => ['self::isInt()', 5.05, false];
        yield 'isNumeric() on \'5\'' => ['self::isNumeric()', '5', true];
        yield 'isNumeric() on 5.05' => ['self::isNumeric()', 5.05, true];
        yield 'isNumeric() on \'abc\'' => ['self::isNumeric()', 'abc', false];
        yield 'isScalar() on \'abc\'' => ['self::isScalar()', 'abc', true];
        yield 'isScalar() on null' => ['self::isScalar()', null, false];
        yield 'isScalar() on []' => ['self::isScalar()', [], false];
        yield 'isIterable() on []' => ['self::isIterable()', [], true];
        yield 'isIterable() on new \\ArrayObject([])' => ['self::isIterable()', new \ArrayObject([]), true];
        yield 'isIterable() on \'abc\'' => ['self::isIterable()', 'abc', false];
        yield 'isNan() on NAN' => ['self::isNan()', NAN, true];
        yield 'isNan() on 5' => ['self::isNan()', 5, false];
        yield 'isNan() on \'abc\'' => ['self::isNan()', 'abc', false];
        yield 'isNan() on []' => ['self::isNan()', [], false];
        yield 'isFinite() on 5' => ['self::isFinite()', 5, true];
        yield 'isFinite() on INF' => ['self::isFinite()', INF, false];
        yield 'isFinite() on \'5\'' => ['self::isFinite()', '5', false];
        yield 'isFinite() on null' => ['self::isFinite()', null, false];
        yield 'isInfinite() on INF' => ['self::isInfinite()', INF, true];
        yield 'isInfinite() on 5' => ['self::isInfinite()', 5, false];
        yield 'isInfinite() on \'abc\'' => ['self::isInfinite()', 'abc', false];
        yield 'fileExists() on __FILE__' => ['self::fileExists()', __FILE__, true];
        yield 'fileExists() on \'/nope/nope\'' => ['self::fileExists()', '/nope/nope', false];
        yield 'fileExists() on 5' => ['self::fileExists()', 5, false];
        yield 'fileExists() on []' => ['self::fileExists()', [], false];
        yield 'directoryExists() on __DIR__' => ['self::directoryExists()', __DIR__, true];
        yield 'directoryExists() on __FILE__' => ['self::directoryExists()', __FILE__, false];
        yield 'directoryExists() on null' => ['self::directoryExists()', null, false];
        yield 'isReadable() on __FILE__' => ['self::isReadable()', __FILE__, true];
        yield 'isReadable() on \'/nope/nope\'' => ['self::isReadable()', '/nope/nope', false];
        yield 'isReadable() on []' => ['self::isReadable()', [], false];
        yield 'callback(fn ($x) => $x === 1) on 1' => ['self::callback(fn ($x) => $x === 1)', 1, true];
        yield 'callback(fn ($x) => $x === 1) on \'1\'' => ['self::callback(fn ($x) => $x === 1)', '1', false];
        yield 'logicalNot(self::equalTo(5)) on 5' => ['self::logicalNot(self::equalTo(5))', 5, false];
        yield 'logicalNot(self::equalTo(5)) on 9' => ['self::logicalNot(self::equalTo(5))', 9, true];
        yield 'logicalNot(self::equalTo(5)) on \'5\'' => ['self::logicalNot(self::equalTo(5))', '5', false];
        yield 'logicalOr(self::isNull(), self::greaterThan(5)) on null' => ['self::logicalOr(self::isNull(), self::greaterThan(5))', null, true];
        yield 'logicalOr(self::isNull(), self::greaterThan(5)) on 9' => ['self::logicalOr(self::isNull(), self::greaterThan(5))', 9, true];
        yield 'logicalOr(self::isNull(), self::greaterThan(5)) on 1' => ['self::logicalOr(self::isNull(), self::greaterThan(5))', 1, false];
        yield 'logicalAnd(self::greaterThan(1), self::lessThan(9)) on 5' => ['self::logicalAnd(self::greaterThan(1), self::lessThan(9))', 5, true];
        yield 'logicalAnd(self::greaterThan(1), self::lessThan(9)) on 9' => ['self::logicalAnd(self::greaterThan(1), self::lessThan(9))', 9, false];
        yield 'logicalAnd(self::greaterThan(1), self::lessThan(9)) on 1' => ['self::logicalAnd(self::greaterThan(1), self::lessThan(9))', 1, false];
        yield 'logicalXor(self::isTrue(), self::isNull()) on true' => ['self::logicalXor(self::isTrue(), self::isNull())', true, true];
        yield 'logicalXor(self::isTrue(), self::isNull()) on null' => ['self::logicalXor(self::isTrue(), self::isNull())', null, true];
        yield 'logicalXor(self::isTrue(), self::isNull()) on false' => ['self::logicalXor(self::isTrue(), self::isNull())', false, false];
    }

    private static function parse(string $constraint): Expr
    {
        $statement = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php {$constraint};")[0];
        \assert($statement instanceof Expression);

        return $statement->expr;
    }
}
