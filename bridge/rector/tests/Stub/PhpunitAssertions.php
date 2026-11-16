<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

// Abstract declarations for reflection only, with PHPUnit's parameter names for named-argument binding;
// runtime parity uses the isolated real PHPUnit tool.
if (!\class_exists(Assert::class, false)) {
    abstract class Assert
    {
        abstract public static function assertArrayHasKey($key, $array, $message = ''): void;

        abstract public static function assertArrayNotHasKey($key, $array, $message = ''): void;

        abstract public static function assertContains($needle, $haystack, $message = ''): void;

        abstract public static function assertContainsOnlyArray($haystack, $message = ''): void;

        abstract public static function assertContainsOnlyBool($haystack, $message = ''): void;

        abstract public static function assertContainsOnlyFloat($haystack, $message = ''): void;

        abstract public static function assertContainsOnlyInstancesOf($className, $haystack, $message = ''): void;

        abstract public static function assertContainsOnlyInt($haystack, $message = ''): void;

        abstract public static function assertContainsOnlyNull($haystack, $message = ''): void;

        abstract public static function assertContainsOnlyString($haystack, $message = ''): void;

        abstract public static function assertCount($expectedCount, $haystack, $message = ''): void;

        abstract public static function assertDirectoryDoesNotExist($directory, $message = ''): void;

        abstract public static function assertDirectoryExists($directory, $message = ''): void;

        abstract public static function assertDirectoryIsNotReadable($directory, $message = ''): void;

        abstract public static function assertDirectoryIsNotWritable($directory, $message = ''): void;

        abstract public static function assertDirectoryIsReadable($directory, $message = ''): void;

        abstract public static function assertDirectoryIsWritable($directory, $message = ''): void;

        abstract public static function assertDoesNotMatchRegularExpression($pattern, $string, $message = ''): void;

        abstract public static function assertEmpty($actual, $message = ''): void;

        abstract public static function assertEquals($expected, $actual, $message = ''): void;

        abstract public static function assertEqualsCanonicalizing($expected, $actual, $message = ''): void;

        abstract public static function assertEqualsIgnoringCase($expected, $actual, $message = ''): void;

        abstract public static function assertFalse($condition, $message = ''): void;

        abstract public static function assertFileDoesNotExist($filename, $message = ''): void;

        abstract public static function assertFileExists($filename, $message = ''): void;

        abstract public static function assertFileIsNotReadable($file, $message = ''): void;

        abstract public static function assertFileIsNotWritable($file, $message = ''): void;

        abstract public static function assertFileIsReadable($file, $message = ''): void;

        abstract public static function assertFileIsWritable($file, $message = ''): void;

        abstract public static function assertFinite($actual, $message = ''): void;

        abstract public static function assertGreaterThan($minimum, $actual, $message = ''): void;

        abstract public static function assertGreaterThanOrEqual($minimum, $actual, $message = ''): void;

        abstract public static function assertInfinite($actual, $message = ''): void;

        abstract public static function assertInstanceOf($expected, $actual, $message = ''): void;

        abstract public static function assertIsArray($actual, $message = ''): void;

        abstract public static function assertIsBool($actual, $message = ''): void;

        abstract public static function assertIsCallable($actual, $message = ''): void;

        abstract public static function assertIsClosedResource($actual, $message = ''): void;

        abstract public static function assertIsFloat($actual, $message = ''): void;

        abstract public static function assertIsInt($actual, $message = ''): void;

        abstract public static function assertIsIterable($actual, $message = ''): void;

        abstract public static function assertIsList($array, $message = ''): void;

        abstract public static function assertIsNotArray($actual, $message = ''): void;

        abstract public static function assertIsNotBool($actual, $message = ''): void;

        abstract public static function assertIsNotCallable($actual, $message = ''): void;

        abstract public static function assertIsNotClosedResource($actual, $message = ''): void;

        abstract public static function assertIsNotFloat($actual, $message = ''): void;

        abstract public static function assertIsNotInt($actual, $message = ''): void;

        abstract public static function assertIsNotIterable($actual, $message = ''): void;

        abstract public static function assertIsNotNumeric($actual, $message = ''): void;

        abstract public static function assertIsNotObject($actual, $message = ''): void;

        abstract public static function assertIsNotReadable($filename, $message = ''): void;

        abstract public static function assertIsNotResource($actual, $message = ''): void;

        abstract public static function assertIsNotScalar($actual, $message = ''): void;

        abstract public static function assertIsNotString($actual, $message = ''): void;

        abstract public static function assertIsNotWritable($filename, $message = ''): void;

        abstract public static function assertIsNumeric($actual, $message = ''): void;

        abstract public static function assertIsObject($actual, $message = ''): void;

        abstract public static function assertIsReadable($filename, $message = ''): void;

        abstract public static function assertIsResource($actual, $message = ''): void;

        abstract public static function assertIsScalar($actual, $message = ''): void;

        abstract public static function assertIsString($actual, $message = ''): void;

        abstract public static function assertIsWritable($filename, $message = ''): void;

        abstract public static function assertJson($actual, $message = ''): void;

        abstract public static function assertLessThan($maximum, $actual, $message = ''): void;

        abstract public static function assertLessThanOrEqual($maximum, $actual, $message = ''): void;

        abstract public static function assertMatchesRegularExpression($pattern, $string, $message = ''): void;

        abstract public static function assertNan($actual, $message = ''): void;

        abstract public static function assertNotContains($needle, $haystack, $message = ''): void;

        abstract public static function assertNotEmpty($actual, $message = ''): void;

        abstract public static function assertNotEquals($expected, $actual, $message = ''): void;

        abstract public static function assertNotEqualsIgnoringCase($expected, $actual, $message = ''): void;

        abstract public static function assertNotFalse($condition, $message = ''): void;

        abstract public static function assertNotInstanceOf($expected, $actual, $message = ''): void;

        abstract public static function assertNotNull($actual, $message = ''): void;

        abstract public static function assertNotRegExp($pattern, $string, $message = ''): void;

        abstract public static function assertNotSame($expected, $actual, $message = ''): void;

        abstract public static function assertNotTrue($condition, $message = ''): void;

        abstract public static function assertNull($actual, $message = ''): void;

        abstract public static function assertObjectEquals($expected, $actual, $method = 'equals', $message = ''): void;

        abstract public static function assertObjectHasProperty($propertyName, $object, $message = ''): void;

        abstract public static function assertObjectNotHasProperty($propertyName, $object, $message = ''): void;

        abstract public static function assertRegExp($pattern, $string, $message = ''): void;

        abstract public static function assertSame($expected, $actual, $message = ''): void;

        abstract public static function assertSameSize($expected, $actual, $message = ''): void;

        abstract public static function assertStringContainsString($needle, $haystack, $message = ''): void;

        abstract public static function assertStringContainsStringIgnoringCase($needle, $haystack, $message = ''): void;

        abstract public static function assertStringContainsStringIgnoringLineEndings($needle, $haystack, $message = ''): void;

        abstract public static function assertStringEndsNotWith($suffix, $string, $message = ''): void;

        abstract public static function assertStringEndsWith($suffix, $string, $message = ''): void;

        abstract public static function assertStringEqualsStringIgnoringLineEndings($expected, $actual, $message = ''): void;

        abstract public static function assertStringEqualsStringIgnoringWhitespace($expected, $actual, $message = ''): void;

        abstract public static function assertStringNotContainsString($needle, $haystack, $message = ''): void;

        abstract public static function assertStringNotContainsStringIgnoringCase($needle, $haystack, $message = ''): void;

        abstract public static function assertStringNotEqualsStringIgnoringWhitespace($expected, $actual, $message = ''): void;

        abstract public static function assertStringStartsNotWith($prefix, $string, $message = ''): void;

        abstract public static function assertStringStartsWith($prefix, $string, $message = ''): void;

        abstract public static function assertThat($value, $constraint, $message = ''): void;

        abstract public static function assertTrue($condition, $message = ''): void;

        abstract public static function fail($message = ''): void;
    }
}

if (!\class_exists(TestCase::class, false)) {
    abstract class TestCase extends Assert
    {
        abstract protected function assertPreConditions(): void;

        abstract protected function assertPostConditions(): void;

        abstract protected function createMock(string $originalClassName): void;
    }
}
