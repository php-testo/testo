<?php

\Testo\Expect::exception(\RuntimeException::class)->withMessage('boom');
-----
<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessage('boom');
