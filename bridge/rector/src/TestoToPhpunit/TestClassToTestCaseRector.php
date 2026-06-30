<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: turn a Testo test class into a PHPUnit one by adding
 * `extends \PHPUnit\Framework\TestCase` and reconciling Testo's test discovery
 * with PHPUnit's.
 *
 * @todo Blocked by structural mismatches between the two models:
 *   - Testo test classes have NO required base class; PHPUnit requires extending
 *     `TestCase`. Naively adding `extends TestCase` is unsafe when the class
 *     already extends something else (single-inheritance clash) and may pull in
 *     PHPUnit lifecycle semantics the original class never opted into.
 *   - Method discovery differs: Testo marks tests with a class- or method-level
 *     `#[Testo\Test]` attribute and imposes no naming convention, while PHPUnit
 *     discovers `test*`-named methods OR methods carrying
 *     `#[PHPUnit\Framework\Attributes\Test]`. A class-level `#[Testo\Test]`
 *     (every public method is a test) has no direct PHPUnit equivalent and would
 *     have to be expanded to a per-method `#[Test]` attribute or method renames,
 *     which requires resolving which public methods are genuinely tests vs.
 *     helpers — not decidable from syntax alone.
 *   - Constructor signatures, DI-injected parameters, and data-provider wiring
 *     would all need reconciling. This is a whole-class structural transform, not
 *     a local node rewrite, so it is left for manual conversion.
 */
final class TestClassToTestCaseRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: would add `extends \PHPUnit\Framework\TestCase` and reconcile Testo `#[Test]` discovery with PHPUnit method discovery (not implemented)',
            [],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        return null;
    }
}
