<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('boom');
$this->expectExceptionCode(7);
-----
<?php

\Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
