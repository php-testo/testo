<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('boom');
-----
<?php

\Testo\Expect::exception(\RuntimeException::class)->withMessage('boom');
