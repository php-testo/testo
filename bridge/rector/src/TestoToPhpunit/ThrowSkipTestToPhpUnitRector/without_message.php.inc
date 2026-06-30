<?php

throw new \Testo\Core\Exception\SkipTest();
-----
<?php

$this->markTestSkipped();
