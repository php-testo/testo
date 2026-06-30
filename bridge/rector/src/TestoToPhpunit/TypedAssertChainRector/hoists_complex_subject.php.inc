<?php

\Testo\Assert::array($log->all())->isList();
-----
<?php

$value = $log->all();
$this->assertIsArray($value);
$this->assertIsList($value);
