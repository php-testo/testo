<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts PHPUnit's `#[Repeat]` / `#[Retry]` method attributes into their Testo counterparts.
 *
 *   - #[PHPUnit\Framework\Attributes\Repeat($times)]              → #[\Testo\Repeat(times: $times)]
 *   - #[PHPUnit\Framework\Attributes\Repeat($times, $threshold)]  → #[\Testo\Repeat(times: $times, maxFailures: $threshold - 1)]
 *   - #[PHPUnit\Framework\Attributes\Retry($maxAttempts)]         → #[\Testo\Retry(maxAttempts: $maxAttempts)]
 *
 * `times` and `maxAttempts` carry over verbatim. The one non-mechanical bit is PHPUnit's
 * `failureThreshold` (default 1): it is the failure **count that aborts** the repeat loop, whereas
 * Testo's `maxFailures` (default 0) is the number of failures **tolerated** before the loop fails —
 * off by one. So `maxFailures = failureThreshold - 1`, and the PHPUnit default of `1` maps to the
 * Testo default of `0`, which is therefore omitted rather than emitted.
 *
 * PHPUnit's `Repeat`/`Retry` are method-only, so no target reconciliation is needed on the Testo
 * side (Testo's attributes additionally allow class/function targets, a strict superset).
 */
#[TestRectorFixtures('RepeatRetryToTestoRector')]
final class RepeatRetryToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit #[Repeat]/#[Retry] attributes into Testo #[\Testo\Repeat]/#[\Testo\Retry]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Repeat(5, 2)]
                        PHP,
                    <<<'PHP'
                        #[\Testo\Repeat(times: 5, maxFailures: 1)]
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Attribute::class];
    }

    /**
     * @param Attribute $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\Repeat')) {
            return $this->convertRepeat($node);
        }

        if ($this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\Retry')) {
            return $this->convertRetry($node);
        }

        return null;
    }

    private function convertRepeat(Attribute $node): ?Attribute
    {
        $times = $node->args[0] ?? null;
        if (!$times instanceof Arg) {
            return null;
        }

        $args = [new Arg($times->value, name: new Identifier('times'))];

        $threshold = $node->args[1] ?? null;
        if ($threshold instanceof Arg && ($maxFailures = $this->thresholdToMaxFailures($threshold->value)) !== null) {
            $args[] = new Arg($maxFailures, name: new Identifier('maxFailures'));
        }

        $node->name = new FullyQualified('Testo\\Repeat');
        $node->args = $args;

        return $node;
    }

    private function convertRetry(Attribute $node): ?Attribute
    {
        $maxAttempts = $node->args[0] ?? null;
        if (!$maxAttempts instanceof Arg) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Retry');
        $node->args = [new Arg($maxAttempts->value, name: new Identifier('maxAttempts'))];

        return $node;
    }

    /**
     * `failureThreshold - 1` as a Testo `maxFailures` expression, or null when it collapses to the
     * default `0` (a literal threshold of `1` or less) and should be omitted.
     */
    private function thresholdToMaxFailures(Node\Expr $threshold): ?Node\Expr
    {
        if ($threshold instanceof Int_) {
            return $threshold->value > 1 ? new Int_($threshold->value - 1) : null;
        }

        return new Minus($threshold, new Int_(1));
    }
}
