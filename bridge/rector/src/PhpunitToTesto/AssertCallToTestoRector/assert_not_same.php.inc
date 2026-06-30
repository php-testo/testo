<?php

$this->assertNotSame(3, $result);
-----
<?php

\Testo\Assert::notSame($result, 3);
