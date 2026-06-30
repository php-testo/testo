<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert Pest architecture tests (`arch()`/`arch('preset')->...`,
 * e.g. `arch()->expect('App')->toBeClasses()->not->toBeFinal()`).
 *
 * @todo NOT CONVERTIBLE — Pest's `arch()` API has NO Testo equivalent.
 *
 * Pest's architecture testing is a dedicated DSL that introspects the codebase
 * (dependency direction, namespace rules, class shape, naming) and is evaluated
 * by Pest's own arch engine. Testo ships no architecture-assertion subsystem, so
 * there is no target API to translate these expectations into — this is not a
 * "restructuring is hard" case but a genuine absence of a counterpart. The rule
 * exists only to DOCUMENT that arch tests cannot be migrated automatically and
 * is NOT registered in any set.
 *
 * MANUAL WORK: keep the architecture checks in Pest, or reimplement them with a
 * separate dedicated tool (e.g. a static-analysis / architecture-rule package).
 * There is no mechanical Testo translation.
 */
final class ArchTestRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'NOT CONVERTIBLE (not implemented): Pest `arch()` tests have no Testo equivalent. Documented only; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        arch('app')
                            ->expect('App')
                            ->not->toBeFinal();
                        PHP,
                    <<<'PHP'
                        // No Testo equivalent: keep in Pest or use a dedicated
                        // architecture-rule tool. Left unchanged.
                        arch('app')
                            ->expect('App')
                            ->not->toBeFinal();
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
        // Intentionally a no-op: there is no Testo target for arch() tests.
        return null;
    }
}
