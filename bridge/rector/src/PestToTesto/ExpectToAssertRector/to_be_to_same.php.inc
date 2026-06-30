<?php

expect($result)->toBe(3);
-----
<?php

\Testo\Assert::same($result, 3);
