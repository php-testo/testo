<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

// `self::` at file scope (outside any class) — activeDeclaration returns '' and the
// invocation is skipped (non-detectable), exercising both the empty-class guard and
// the activeDeclaration no-match path.
self::notAClass();
