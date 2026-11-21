<?php

/**
 * Namespaced counterparts of {@see probe_special_function()} and friends, adapted from the
 * shipmonk-rnd/turbocov test fixtures (MIT): here `assert()` and `strlen()` resolve at runtime.
 */

namespace Testo\CoverageProbe\SkippedOperands;

function assertion($a, $b) {
    assert($a
        || $b);
    return 1;
}
function function_call($a) {
    return strlen($a
        ?? 'none');
}
function next_argument($a, $b, $c) {
    return max($a
        ?? $b, $c);
}
