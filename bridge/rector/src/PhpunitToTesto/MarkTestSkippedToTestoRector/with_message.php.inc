<?php

$this->markTestSkipped('not ready');
-----
<?php

throw new \Testo\Core\Exception\SkipTest('not ready');
