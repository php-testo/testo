<?php

namespace ProviderImportedAttribute;

use PHPUnit\Framework\Attributes\DataProvider;

class ImportedProviderTest
{
    /**
     * @dataProvider cases
     */
    public function testX(int $a): void
    {
    }
}
-----
<?php

namespace ProviderImportedAttribute;

use PHPUnit\Framework\Attributes\DataProvider;

class ImportedProviderTest
{
    #[\Testo\Data\DataProvider('cases')]
    public function testX(int $a): void
    {
    }
}
