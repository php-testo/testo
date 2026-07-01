<?php

// A convertible chain, but at namespace level: the emitted $this->assert* would have no method
// scope, so the chain is left untouched.
\Testo\Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
