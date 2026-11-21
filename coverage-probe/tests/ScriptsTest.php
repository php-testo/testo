<?php

declare(strict_types=1);

namespace Tests\CoverageProbe;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\CoverageProbe\Constructs;
use Testo\CoverageProbe\EdgeCases;
use Testo\CoverageProbe\Modern;
use Testo\Test;

require_once \dirname(__DIR__) . '/src/Constructs.php';
require_once \dirname(__DIR__) . '/src/EdgeCases.php';
require_once \dirname(__DIR__) . '/src/Modern.php';

#[Test]
final class ScriptsTest
{
    #[Covers(Constructs\A::class)]
    #[Covers(Constructs\B::class)]
    #[Covers(Constructs\T::class)]
    #[Covers('Testo\CoverageProbe\Constructs\script')]
    public function constructs(): void
    {
        Assert::same(Constructs\script(), 0);
    }

    #[Covers(EdgeCases\Base::class)]
    #[Covers(EdgeCases\Kind::class)]
    #[Covers(EdgeCases\Square::class)]
    #[Covers('Testo\CoverageProbe\EdgeCases\divide')]
    #[Covers('Testo\CoverageProbe\EdgeCases\withFinally')]
    #[Covers('Testo\CoverageProbe\EdgeCases\variadic')]
    #[Covers('Testo\CoverageProbe\EdgeCases\shortCircuit')]
    #[Covers('Testo\CoverageProbe\EdgeCases\lazy')]
    #[Covers('Testo\CoverageProbe\EdgeCases\declareConditional')]
    #[Covers('Testo\CoverageProbe\EdgeCases\script')]
    public function edgeCases(): void
    {
        Assert::same(EdgeCases\script(), 3);
    }

    #[Covers(Modern\Temperature::class)]
    #[Covers(Modern\Counter::class)]
    #[Covers(Modern\Point::class)]
    #[Covers(Modern\Text::class)]
    #[Covers('Testo\CoverageProbe\Modern\zone')]
    #[Covers('Testo\CoverageProbe\Modern\script')]
    public function modern(): void
    {
        Assert::same(Modern\script(), '68,unnamed,3,3,hello-world,1.5,2');
    }
}
