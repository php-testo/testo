<?php

expect($items)->toHaveCount(3);
-----
<?php

\Testo\Assert::count($items, 3);
