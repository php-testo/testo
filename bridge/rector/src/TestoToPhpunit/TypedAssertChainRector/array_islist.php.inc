<?php

\Testo\Assert::array($items)->isList();
-----
<?php

$this->assertIsArray($items);
$this->assertIsList($items);
