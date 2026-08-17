<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts Testo's `#[\Testo\Repeat]` / `#[\Testo\Retry]` **method** attributes into PHPUnit's
 * `#[Repeat]` / `#[Retry]` (available since PHPUnit 13.3):
 *
 *   - #[\Testo\Repeat(times: N)]                    → #[\PHPUnit\Framework\Attributes\Repeat(N)]
 *   - #[\Testo\Repeat(times: N, maxFailures: M)]    → #[\PHPUnit\Framework\Attributes\Repeat(N, M + 1)]
 *   - #[\Testo\Retry(maxAttempts: N)]              → #[\PHPUnit\Framework\Attributes\Retry(N)]
 *
 * The mirror of {@see \Testo\Bridge\Rector\PhpunitToTesto\RepeatRetryToTestoRector}. Testo's
 * `maxFailures` (tolerated failures, default 0) maps to PHPUnit's `failureThreshold` (aborting
 * failure count, default 1) as `failureThreshold = maxFailures + 1`; the Testo default `0` folds
 * back to PHPUnit's default `1` and is omitted. PHPUnit's attributes are positional
 * (`@no-named-arguments`), so the emitted arguments carry no names. Testo's defaults are made
 * explicit where PHPUnit has no matching default drop (`times` → `2`, `maxAttempts` → `3`).
 *
 * **Residuals:** Testo's `markFlaky` flag is dropped — PHPUnit has no flaky-marking equivalent.
 * Only **method-level** attributes are converted: PHPUnit's `Repeat`/`Retry` are `TARGET_METHOD`
 * only, whereas Testo additionally allows them on a class or a function, which have no PHPUnit
 * counterpart and are left untouched. See TODO.md.
 */
#[TestRectorFixtures('RepeatRetryRector')]
final class RepeatRetryRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo #[Repeat]/#[Retry] method attributes into PHPUnit #[Repeat]/#[Retry]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Repeat(times: 5, maxFailures: 1)]
                        public function test(): void {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Repeat(5, 2)]
                        public function test(): void {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $changed = false;

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($this->isName($attribute->name, 'Testo\\Repeat')) {
                    $this->convertRepeat($attribute);
                    $changed = true;
                } elseif ($this->isName($attribute->name, 'Testo\\Retry')) {
                    $this->convertRetry($attribute);
                    $changed = true;
                }
            }
        }

        return $changed ? $node : null;
    }

    private function convertRepeat(Attribute $attribute): void
    {
        $times = $this->argValue($attribute->args, 'times', 0) ?? new Int_(2);
        $args = [new Arg($times)];

        $maxFailures = $this->argValue($attribute->args, 'maxFailures', 1);
        if ($maxFailures !== null && ($threshold = $this->maxFailuresToThreshold($maxFailures)) !== null) {
            $args[] = new Arg($threshold);
        }

        $attribute->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\Repeat');
        $attribute->args = $args;
    }

    private function convertRetry(Attribute $attribute): void
    {
        $maxAttempts = $this->argValue($attribute->args, 'maxAttempts', 0) ?? new Int_(3);

        $attribute->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\Retry');
        $attribute->args = [new Arg($maxAttempts)];
    }

    /**
     * `maxFailures + 1` as a PHPUnit `failureThreshold` expression, or null when it collapses to the
     * default `1` (a literal `maxFailures` of `0`) and should be omitted.
     */
    private function maxFailuresToThreshold(Node\Expr $maxFailures): ?Node\Expr
    {
        if ($maxFailures instanceof Int_) {
            return $maxFailures->value > 0 ? new Int_($maxFailures->value + 1) : null;
        }

        return new Plus($maxFailures, new Int_(1));
    }

    /**
     * The value of the named argument `$name`, or the positional argument at `$position` when it
     * carries no name; null when neither is present.
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     */
    private function argValue(array $args, string $name, int $position): ?Node\Expr
    {
        foreach ($args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === $name) {
                return $arg->value;
            }
        }

        $arg = $args[$position] ?? null;
        return $arg instanceof Arg && $arg->name === null ? $arg->value : null;
    }
}
