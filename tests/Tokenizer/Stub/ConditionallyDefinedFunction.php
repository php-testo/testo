<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

/**
 * Stub: the function is inside an always-false condition so the tokenizer sees it
 * as a declared function but PHP never actually registers it. This exercises the
 * LocatorException reporter/skip path in DefinitionLocator::getFunctions().
 */
if (false) {
    function conditionallyUndefinedFunction9876(): void {}
}
