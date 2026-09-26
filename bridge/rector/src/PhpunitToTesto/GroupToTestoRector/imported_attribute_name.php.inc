<?php

namespace GroupImportedAttribute;

use PHPUnit\Framework\Attributes\Group;

class ImportedGroupTest
{
    /**
     * @group slow
     */
    public function testGrouped(): void
    {
    }
}
-----
<?php

namespace GroupImportedAttribute;

use PHPUnit\Framework\Attributes\Group;

class ImportedGroupTest
{
    #[\Testo\Filter\Group('slow')]
    public function testGrouped(): void
    {
    }
}
