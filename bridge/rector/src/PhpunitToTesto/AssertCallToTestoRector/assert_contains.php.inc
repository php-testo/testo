<?php

$this->assertContains($needle, $haystack);
-----
<?php

\Testo\Assert::contains($haystack, $needle);
