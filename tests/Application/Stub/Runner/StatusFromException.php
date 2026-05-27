<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Runner;

use Testo\Core\Exception\CancelTest;
use Testo\Core\Exception\SkipTest;
use Testo\Test;

/**
 * Stub tests that throw status-bearing exceptions from the test body.
 *
 * Each method is exercised through the real runner via
 * {@see \Testo\Testing\Traits\TestRunner::runTest()} so we can observe how
 * {@see \Testo\Application\Internal\Runner\TestRunner} maps the throw to a
 * {@see \Testo\Core\Value\Status}.
 */
#[Test]
final class StatusFromException
{
    public const SKIP_MESSAGE = 'pdo_mysql required';
    public const CANCEL_MESSAGE = 'deadline exceeded';
    public const ERROR_MESSAGE = 'unexpected boom';

    public function throwsSkipTest(): never
    {
        throw new SkipTest(self::SKIP_MESSAGE);
    }

    public function throwsCancelTest(): never
    {
        throw new CancelTest(self::CANCEL_MESSAGE);
    }

    public function throwsGenericException(): never
    {
        throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    public function throwsSubclassedSkipTest(): never
    {
        throw new class(self::SKIP_MESSAGE) extends SkipTest {};
    }
}
