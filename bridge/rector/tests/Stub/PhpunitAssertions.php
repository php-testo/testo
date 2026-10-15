<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

// Abstract declarations for reflection only; runtime parity uses the isolated real PHPUnit tool.
if (!\class_exists(Assert::class, false)) {
    abstract class Assert
    {
        abstract public static function assertArrayHasKey(...$arguments): void;

        abstract public static function assertArrayNotHasKey(...$arguments): void;

        abstract public static function assertContains(...$arguments): void;

        abstract public static function assertContainsOnlyArray(...$arguments): void;

        abstract public static function assertContainsOnlyBool(...$arguments): void;

        abstract public static function assertContainsOnlyFloat(...$arguments): void;

        abstract public static function assertContainsOnlyInstancesOf(...$arguments): void;

        abstract public static function assertContainsOnlyInt(...$arguments): void;

        abstract public static function assertContainsOnlyNull(...$arguments): void;

        abstract public static function assertContainsOnlyString(...$arguments): void;

        abstract public static function assertCount(...$arguments): void;

        abstract public static function assertDirectoryDoesNotExist(...$arguments): void;

        abstract public static function assertDirectoryExists(...$arguments): void;

        abstract public static function assertDirectoryIsNotReadable(...$arguments): void;

        abstract public static function assertDirectoryIsNotWritable(...$arguments): void;

        abstract public static function assertDirectoryIsReadable(...$arguments): void;

        abstract public static function assertDirectoryIsWritable(...$arguments): void;

        abstract public static function assertDoesNotMatchRegularExpression(...$arguments): void;

        abstract public static function assertEmpty(...$arguments): void;

        abstract public static function assertEquals(...$arguments): void;

        abstract public static function assertEqualsCanonicalizing(...$arguments): void;

        abstract public static function assertEqualsIgnoringCase(...$arguments): void;

        abstract public static function assertFalse(...$arguments): void;

        abstract public static function assertFileDoesNotExist(...$arguments): void;

        abstract public static function assertFileExists(...$arguments): void;

        abstract public static function assertFileIsNotReadable(...$arguments): void;

        abstract public static function assertFileIsNotWritable(...$arguments): void;

        abstract public static function assertFileIsReadable(...$arguments): void;

        abstract public static function assertFileIsWritable(...$arguments): void;

        abstract public static function assertFinite(...$arguments): void;

        abstract public static function assertGreaterThan(...$arguments): void;

        abstract public static function assertGreaterThanOrEqual(...$arguments): void;

        abstract public static function assertInfinite(...$arguments): void;

        abstract public static function assertInstanceOf(...$arguments): void;

        abstract public static function assertIsArray(...$arguments): void;

        abstract public static function assertIsBool(...$arguments): void;

        abstract public static function assertIsCallable(...$arguments): void;

        abstract public static function assertIsClosedResource(...$arguments): void;

        abstract public static function assertIsFloat(...$arguments): void;

        abstract public static function assertIsInt(...$arguments): void;

        abstract public static function assertIsIterable(...$arguments): void;

        abstract public static function assertIsList(...$arguments): void;

        abstract public static function assertIsNotArray(...$arguments): void;

        abstract public static function assertIsNotBool(...$arguments): void;

        abstract public static function assertIsNotCallable(...$arguments): void;

        abstract public static function assertIsNotClosedResource(...$arguments): void;

        abstract public static function assertIsNotFloat(...$arguments): void;

        abstract public static function assertIsNotInt(...$arguments): void;

        abstract public static function assertIsNotIterable(...$arguments): void;

        abstract public static function assertIsNotNumeric(...$arguments): void;

        abstract public static function assertIsNotObject(...$arguments): void;

        abstract public static function assertIsNotReadable(...$arguments): void;

        abstract public static function assertIsNotResource(...$arguments): void;

        abstract public static function assertIsNotScalar(...$arguments): void;

        abstract public static function assertIsNotString(...$arguments): void;

        abstract public static function assertIsNotWritable(...$arguments): void;

        abstract public static function assertIsNumeric(...$arguments): void;

        abstract public static function assertIsObject(...$arguments): void;

        abstract public static function assertIsReadable(...$arguments): void;

        abstract public static function assertIsResource(...$arguments): void;

        abstract public static function assertIsScalar(...$arguments): void;

        abstract public static function assertIsString(...$arguments): void;

        abstract public static function assertIsWritable(...$arguments): void;

        abstract public static function assertJson(...$arguments): void;

        abstract public static function assertLessThan(...$arguments): void;

        abstract public static function assertLessThanOrEqual(...$arguments): void;

        abstract public static function assertMatchesRegularExpression(...$arguments): void;

        abstract public static function assertNan(...$arguments): void;

        abstract public static function assertNotContains(...$arguments): void;

        abstract public static function assertNotEmpty(...$arguments): void;

        abstract public static function assertNotEquals(...$arguments): void;

        abstract public static function assertNotEqualsIgnoringCase(...$arguments): void;

        abstract public static function assertNotFalse(...$arguments): void;

        abstract public static function assertNotInstanceOf(...$arguments): void;

        abstract public static function assertNotNull(...$arguments): void;

        abstract public static function assertNotRegExp(...$arguments): void;

        abstract public static function assertNotSame(...$arguments): void;

        abstract public static function assertNotTrue(...$arguments): void;

        abstract public static function assertNull(...$arguments): void;

        abstract public static function assertObjectHasProperty(...$arguments): void;

        abstract public static function assertObjectNotHasProperty(...$arguments): void;

        abstract public static function assertRegExp(...$arguments): void;

        abstract public static function assertSame(...$arguments): void;

        abstract public static function assertSameSize(...$arguments): void;

        abstract public static function assertStringContainsString(...$arguments): void;

        abstract public static function assertStringContainsStringIgnoringCase(...$arguments): void;

        abstract public static function assertStringContainsStringIgnoringLineEndings(...$arguments): void;

        abstract public static function assertStringEndsNotWith(...$arguments): void;

        abstract public static function assertStringEndsWith(...$arguments): void;

        abstract public static function assertStringEqualsStringIgnoringLineEndings(...$arguments): void;

        abstract public static function assertStringEqualsStringIgnoringWhitespace(...$arguments): void;

        abstract public static function assertStringNotContainsString(...$arguments): void;

        abstract public static function assertStringNotContainsStringIgnoringCase(...$arguments): void;

        abstract public static function assertStringNotEqualsStringIgnoringWhitespace(...$arguments): void;

        abstract public static function assertStringStartsNotWith(...$arguments): void;

        abstract public static function assertStringStartsWith(...$arguments): void;

        abstract public static function assertTrue(...$arguments): void;

        abstract public static function fail(...$arguments): void;
    }
}

if (!\class_exists(TestCase::class, false)) {
    abstract class TestCase extends Assert
    {
        abstract protected function createMock(string $originalClassName): void;
    }
}
