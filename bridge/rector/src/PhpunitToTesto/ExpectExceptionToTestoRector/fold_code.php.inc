<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionCode(7);
-----
<?php

\Testo\Expect::exception(\RuntimeException::class)->withCode(7);
