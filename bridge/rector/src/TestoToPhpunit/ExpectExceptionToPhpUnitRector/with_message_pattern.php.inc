<?php

\Testo\Expect::exception(\RuntimeException::class)->withMessagePattern('/boom/');
-----
<?php

$this->expectException(\RuntimeException::class);
$this->expectExceptionMessageMatches('/boom/');
