<?php

self::markTestSkipped('x');
-----
<?php

throw new \Testo\Core\Exception\SkipTest('x');
