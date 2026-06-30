<?php

$this->assertCount(3, $items);
-----
<?php

\Testo\Assert::count($items, 3);
