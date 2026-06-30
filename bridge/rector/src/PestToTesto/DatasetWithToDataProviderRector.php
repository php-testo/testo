<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert a Pest data-driven test `test(...)->with([...])` (or a named
 * `dataset()` reference) into Testo's `#[\Testo\Data\DataProvider]` /
 * `#[\Testo\Data\DataSet]` parameterisation.
 *
 * @todo NOT IMPLEMENTED — depends on the test having already become a method,
 *       which Rector cannot do (see {@see TestFunctionToMethodRector}).
 *
 * In Pest the dataset is attached fluently to a top-level `test()` call and the
 * dataset rows are spread as positional CLOSURE PARAMETERS. In Testo, data is
 * attached as an ATTRIBUTE on a test method and rows map to METHOD PARAMETERS:
 *   - inline rows -> one `#[\Testo\Data\DataSet([...])]` per row (or a provider);
 *   - a named `dataset('name', ...)` -> a `#[\Testo\Data\DataProvider]` pointing
 *     at a provider method/source.
 * Both require a method with a real parameter list to bind onto, and a named
 * `dataset()` defined elsewhere in the file (or a shared Datasets file) would
 * have to be relocated to a provider. None of this exists until the functional
 * test is restructured into a class method first.
 *
 * MANUAL WORK: once the test is a method, give it parameters matching the row
 * shape, then add `#[\Testo\Data\DataSet([...])]` for inline rows or
 * `#[\Testo\Data\DataProvider(...)]` referencing a provider that yields the rows.
 */
final class DatasetWithToDataProviderRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `->with([...])` / `dataset()` into Testo `Data\DataSet` / `Data\DataProvider`. Requires a method to attach to; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        test('doubles', function ($in, $out) {
                            expect($in * 2)->toBe($out);
                        })->with([[1, 2], [2, 4]]);
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        #[\Testo\Data\DataSet([1, 2])]
                        #[\Testo\Data\DataSet([2, 4])]
                        public function doubles(int $in, int $out): void
                        {
                            \Testo\Assert::same($in * 2, $out);
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Intentionally a no-op: see the class-level @todo.
        return null;
    }
}
