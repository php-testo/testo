<?php

// A mapped assertion, but at namespace level: no `$this`/`self::` target, so it is left untouched.
\Testo\Assert::same($result, 3);
