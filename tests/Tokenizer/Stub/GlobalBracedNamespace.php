<?php

// Global (unnamed) braced namespace block — the `{` immediately follows `namespace`
// with no whitespace token in between, causing registerNamespace to break at line 334.
namespace{
    function globalBracedFunction(): void {}
}
