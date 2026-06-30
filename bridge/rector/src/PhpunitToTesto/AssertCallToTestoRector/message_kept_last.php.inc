<?php

$this->assertSame($expected, $actual, 'values must match');
-----
<?php

\Testo\Assert::same($actual, $expected, 'values must match');
