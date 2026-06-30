<?php

$this->assertInstanceOf(Foo::class, $object);
-----
<?php

\Testo\Assert::instanceOf($object, Foo::class);
