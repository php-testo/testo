<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Core\Value\Status;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\Common;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class CommonTest
{
    use TestRunner;

    #[Test(description: 'A successfully finished test without any assertion marked as Risky')]
    public function noAssertions(): void
    {
        $result = self::runTest([Common::class, 'risky']);
        Assert::same(Status::Risky, $result->status);
    }
}
