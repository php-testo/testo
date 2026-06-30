<?php

\Testo\Assert::same($actual, $expected, 'values must match');
-----
<?php

$this->assertSame($expected, $actual, 'values must match');
