<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Html;

use Testo\Filter\Group;

/**
 * Methods the document tests build results around. Nothing here is ever executed — only reflected on,
 * which is where the report reads a test's line number, parameter names and groups from.
 */
#[Group('reporting')]
final class SampleTestClass
{
    public function passingTest(): void {}

    #[Group('failure')]
    public function failingTest(): void {}

    public function datasetTest(string $input, int $expected): void {}

    public function flakyTest(): void {}
}
