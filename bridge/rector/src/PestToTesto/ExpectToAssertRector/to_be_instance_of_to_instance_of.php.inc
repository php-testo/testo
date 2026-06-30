<?php

expect($object)->toBeInstanceOf(Foo::class);
-----
<?php

\Testo\Assert::instanceOf($object, Foo::class);
