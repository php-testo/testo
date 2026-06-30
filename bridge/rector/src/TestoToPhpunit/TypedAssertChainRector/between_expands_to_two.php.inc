<?php

\Testo\Assert::int($n)->between(1, 10);
-----
<?php

$this->assertIsInt($n);
$this->assertGreaterThanOrEqual(1, $n);
$this->assertLessThanOrEqual(10, $n);
