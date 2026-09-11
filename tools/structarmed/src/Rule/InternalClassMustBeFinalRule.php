<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Testo\Tools\StructArmed\Support\ClassAnnotations;

/**
 * An @internal class with no subclasses must be final: nothing outside the package
 * may rely on it, so leaving it open only invites accidental extension. @api classes
 * are deliberately exempt — they may stay open for downstream subclassing.
 */
final readonly class InternalClassMustBeFinalRule implements RuleInterface
{
    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isClass()
            && ! $classNode->isAbstract
            && $classNode->isInLayer($this->layer)
            && ClassAnnotations::hasInternal($classNode->file, $classNode->shortName());
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        // A class another scanned class extends cannot be made final without breaking the child.
        if ($classNode->isFinal || $classNode->isExtended) {
            return null;
        }

        return new RuleViolation(
            message: \sprintf('Internal class [%s] must be declared final', $classNode->className),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
