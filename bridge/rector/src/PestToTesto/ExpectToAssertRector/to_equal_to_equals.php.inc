<?php

expect($value)->toEqual($expected);
-----
<?php

\Testo\Assert::equals($value, $expected);
