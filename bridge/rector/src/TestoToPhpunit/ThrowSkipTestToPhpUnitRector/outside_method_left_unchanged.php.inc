<?php

// A SkipTest throw, but at namespace level: $this->markTestSkipped() would have no method scope,
// so the throw is left untouched.
throw new \Testo\Core\Exception\SkipTest('not applicable');
