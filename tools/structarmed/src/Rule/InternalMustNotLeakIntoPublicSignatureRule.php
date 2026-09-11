<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Testo\Tools\StructArmed\Support\ClassAnnotations;
use Testo\Tools\StructArmed\Support\ClassSignatures;

use function sprintf;
use function str_contains;

/**
 * A class on the public surface must not name an internal type in its signature.
 * Internal types are implementation detail; surfacing one in a public or protected
 * parameter, return, or property drags it into the contract. Classes that are
 * themselves internal — under an Internal namespace or annotated @internal — are
 * exempt: internal-to-internal coupling stays behind the package boundary (and any
 * cross-package leak is caught by [InternalIsUsableOnlyWithinItsPackageRule]).
 */
final readonly class InternalMustNotLeakIntoPublicSignatureRule implements RuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer) && ! $this->isInternal($classNode);
    }

    public function evaluate(ClassNode $classNode): ?RuleViolation
    {
        return $this->evaluateAll($classNode)[0] ?? null;
    }

    /**
     * @return list<RuleViolation>
     */
    public function evaluateAll(ClassNode $classNode): array
    {
        $violations = [];

        foreach (ClassSignatures::publicTypes($classNode->file, $classNode->shortName()) as $type) {
            if (! str_contains($type, self::SEGMENT)) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: sprintf(
                    '%s [%s] exposes internal type [%s] in its public signature',
                    $classNode->getType(),
                    $classNode->className,
                    $type,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }

    private function isInternal(ClassNode $classNode): bool
    {
        return str_contains($classNode->className, self::SEGMENT)
            || ClassAnnotations::hasInternal($classNode->file, $classNode->shortName());
    }
}
