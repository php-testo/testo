<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Analyser\ClassSignature;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleViolation;
use Boundwize\StructArmed\Rule\SignatureTypesAwareRuleInterface;

/**
 * A class on the public surface must not name an internal type in its signature.
 * Internal types are implementation detail; surfacing one in a public or protected
 * parameter, return, or property drags it into the contract. Classes that are
 * themselves internal — under an Internal namespace or annotated @internal — are
 * exempt: internal-to-internal coupling stays behind the package boundary (and any
 * cross-package leak is caught by [InternalIsUsableOnlyWithinItsPackageRule]).
 */
final readonly class InternalMustNotLeakIntoPublicSignatureRule implements SignatureTypesAwareRuleInterface, MultipleRuleViolationInterface
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

        foreach ($classNode->signatures as $signature) {
            // A privately promoted constructor param is a private property, not public surface.
            if (! $signature->isExposed()
                || ($signature->kind === ClassSignature::PARAM && $signature->isPromoted)
                || ! \str_contains($signature->type, self::SEGMENT)
            ) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] exposes internal type [%s] in its public signature through %s [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $signature->type,
                    $signature->kind,
                    $signature->name,
                ),
                file: $classNode->file,
                line: $signature->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }

    private function isInternal(ClassNode $classNode): bool
    {
        return \str_contains($classNode->className, self::SEGMENT)
            || $classNode->isInternal;
    }
}
