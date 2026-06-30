<?php

throw new \Testo\Core\Exception\SkipTest('not applicable');
-----
<?php

$this->markTestSkipped('not applicable');
