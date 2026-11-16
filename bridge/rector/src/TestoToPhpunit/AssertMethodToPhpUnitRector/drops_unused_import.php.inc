<?php

namespace App\Tests;

use Testo\Assert;
use Testo\Common\Attribute\AssertMethod;

trait ApiAssertions
{
    #[AssertMethod]
    protected function assertJsonResponse($response): void
    {
        Assert::same($response, 200);
    }
}
-----
<?php

namespace App\Tests;

use Testo\Assert;

trait ApiAssertions
{
    protected function assertJsonResponse($response): void
    {
        Assert::same($response, 200);
    }
}
