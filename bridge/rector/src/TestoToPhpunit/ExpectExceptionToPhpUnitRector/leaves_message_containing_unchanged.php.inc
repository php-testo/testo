<?php

\Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom');
