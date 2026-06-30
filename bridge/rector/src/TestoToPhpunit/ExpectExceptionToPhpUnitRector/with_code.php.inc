<?php

\Testo\Expect::exception(\RuntimeException::class)->withCode(7);
-----
<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionCode(7);
