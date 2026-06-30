<?php

\Testo\Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
-----
<?php

$this->assertIsInt($n);
$this->assertGreaterThan(0, $n);
$this->assertLessThanOrEqual(100, $n);
