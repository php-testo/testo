<?php

\Testo\Assert::object($o)->instanceOf(\Foo::class)->hasProperty('id');
-----
<?php

$this->assertIsObject($o);
$this->assertInstanceOf(\Foo::class, $o);
$this->assertObjectHasProperty('id', $o);
