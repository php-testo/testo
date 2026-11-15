<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites PHPUnit's `#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]` into Testo's
 * `#[\Testo\ErrorHandler\WithoutErrorHandler]`.
 *
 * Both are no-argument markers that keep the runner's own error handler off one test, so the
 * rewrite is a plain attribute-name rename. The Testo side comes from the `testo/error-handler`
 * plugin; in a project that does not enable the plugin the converted attribute is inert, exactly
 * as the PHPUnit one is in a project whose tests raise no errors.
 *
 * Residual: PHPUnit allows the attribute on a method only, Testo also on a class and a function,
 * so the conversion is total in this direction and narrowing in the other.
 */
#[TestRectorFixtures('WithoutErrorHandlerRector')]
final class WithoutErrorHandlerRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit #[WithoutErrorHandler] attribute into Testo #[\Testo\ErrorHandler\WithoutErrorHandler]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
                        PHP,
                    <<<'PHP'
                        #[\Testo\ErrorHandler\WithoutErrorHandler]
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
        if (!$this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\WithoutErrorHandler')) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\ErrorHandler\\WithoutErrorHandler');

        return $node;
    }
}
