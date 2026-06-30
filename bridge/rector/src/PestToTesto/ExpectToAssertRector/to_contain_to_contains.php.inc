<?php

expect($haystack)->toContain($needle);
-----
<?php

\Testo\Assert::contains($haystack, $needle);
