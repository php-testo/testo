<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\PestToTesto\ExpectToAssertRector;
use Testo\Bridge\Rector\PestToTesto\TestCallToFunctionRector;

/**
 * Pest -> Testo conversion set.
 *
 * Pest is a functional DSL (file-level `test()`/`it()` closures, `expect()`,
 * `beforeEach()`, modifier chains) while Testo is attribute based. The two
 * registered rules cover the common, deterministic shape of a Pest file:
 *
 *   - {@see TestCallToFunctionRector} restructures each `test()`/`it()` and
 *     `beforeEach()`/`afterEach()`/`beforeAll()`/`afterAll()` call into a free
 *     function carrying the matching Testo attribute (`#[\Testo\Test]` /
 *     `Testo\Lifecycle\*`), deriving the function name from the description and
 *     folding the fluent chain (`->group`, `->covers`, `->throws`, `->skip`,
 *     `->with([...])`) into attributes / body statements.
 *   - {@see ExpectToAssertRector} then maps each `expect($v)->toX(...)`
 *     expectation inside those bodies to `\Testo\Assert::*`.
 *
 * What is NOT handled (left visibly unconverted; see bridge/rector/src/PestToTesto/TODO.md):
 * `describe()` blocks, `uses()`/`dataset()`, named dataset references, `arch()`
 * tests, `$this`-shared state, and any unrecognised modifier.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(TestCallToFunctionRector::class);
    $rectorConfig->rule(ExpectToAssertRector::class);
};
