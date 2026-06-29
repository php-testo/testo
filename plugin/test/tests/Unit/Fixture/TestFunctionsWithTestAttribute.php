<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test;

#[Test]
function functionWithTestAttribute(): void {}

#[Test]
function anotherFunctionWithTestAttribute(): void {}

function functionWithoutTestAttribute(): void {}
