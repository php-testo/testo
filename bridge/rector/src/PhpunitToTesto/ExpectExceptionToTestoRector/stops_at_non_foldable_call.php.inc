<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessageMatches('/boom/');
-----
<?php

\Testo\Expect::exception(\RuntimeException::class);
$this->expectExceptionMessageMatches('/boom/');
