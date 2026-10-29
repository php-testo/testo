<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * Every class-like must state its audience: @api (part of the public contract) or
 * @internal (free to change). An unmarked class leaves consumers guessing what they
 * may depend on.
 */
final readonly class ClassMustDeclareApiOrInternalRule implements RuleInterface
{
    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer);
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        if ($classNode->isApi || $classNode->isInternal) {
            return null;
        }

        return new RuleViolation(
            message: \sprintf('%s [%s] must be annotated @api or @internal', $classNode->getType(), $classNode->className),
            file: $classNode->file,
            line: $classNode->line,
            className: $classNode->className,
            layer: $classNode->layer,
        );
    }
}
