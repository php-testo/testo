<?php

\Testo\Assert::string($s)->contains('foo')->notContains('bar');
-----
<?php

$this->assertIsString($s);
$this->assertStringContainsString('foo', $s);
$this->assertStringNotContainsString('bar', $s);
