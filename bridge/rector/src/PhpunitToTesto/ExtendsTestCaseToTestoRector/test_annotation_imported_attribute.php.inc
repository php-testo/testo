<?php

namespace TestImportedAttribute;

use PHPUnit\Framework\Attributes\Test;

final class ImportedTestAnnotationTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function annotated(): void
    {
    }
}
-----
<?php

namespace TestImportedAttribute;

use PHPUnit\Framework\Attributes\Test;

final class ImportedTestAnnotationTest
{
    #[\Testo\Test]
    public function annotated(): void
    {
    }
}
