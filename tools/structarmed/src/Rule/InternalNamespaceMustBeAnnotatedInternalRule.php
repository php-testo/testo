<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Testo\Tools\StructArmed\Support\ClassAnnotations;

use function str_contains;

/**
 * Anything living under an `Internal` namespace segment is implementation detail and
 * must say so with @internal — the folder alone is a convention, the tag is the contract.
 */
final readonly class InternalNamespaceMustBeAnnotatedInternalRule implements RuleInterface
{
    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer)
            && str_contains($classNode->className, '\\Internal\\');
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return ClassAnnotations::hasInternal($classNode->file, $classNode->shortName()) ? null : new RuleViolation(
            message: \sprintf('%s [%s] lives under an Internal namespace and must be annotated @internal', $classNode->getType(), $classNode->className),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
