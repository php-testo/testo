<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\Common;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class CommonTest
{
    /**
     * A successfully finished test without any assertion marked as Risky
     */
    #[Test]
    public function noAssertions(): void
    {
        $result = TestRunner::runTest([Common::class, 'risky']);
        Assert::same($result->status, Status::Risky);
    }
}
