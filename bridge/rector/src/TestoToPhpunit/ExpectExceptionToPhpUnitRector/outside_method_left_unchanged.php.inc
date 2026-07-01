<?php

// A convertible expectation, but at namespace level: the emitted $this->expect* would have no
// method scope, so the chain is left untouched.
\Testo\Expect::exception(\RuntimeException::class)->withMessage('boom');
