<?php

declare(strict_types=1);

final class ImportGlobalTest
{
    #[\Testo\Test]
    public function works(): void
    {
        \Mockery::mock(\ArrayAccess::class);
    }
}
-----
<?php

declare(strict_types=1);

use Testo\Test;

final class ImportGlobalTest
{
    #[Test]
    public function works(): void
    {
        \Mockery::mock(\ArrayAccess::class);
    }
}
