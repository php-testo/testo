<?php

$this->markTestSkipped();
-----
<?php

throw new \Testo\Core\Exception\SkipTest();
