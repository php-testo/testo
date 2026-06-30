<?php

\Testo\Expect::exception(\RuntimeException::class);
-----
<?php

$this->expectException(\RuntimeException::class);
