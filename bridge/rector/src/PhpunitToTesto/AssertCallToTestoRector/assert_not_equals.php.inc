<?php

$this->assertNotEquals($expected, $value);
-----
<?php

\Testo\Assert::notEquals($value, $expected);
