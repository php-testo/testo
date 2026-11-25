<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Rule;

use Boundwize\StructArmed\Analyser\ClassNode;
use Boundwize\StructArmed\Rule\MultipleRuleViolationInterface;
use Boundwize\StructArmed\Rule\RuleInterface;
use Boundwize\StructArmed\Rule\RuleViolation;

/**
 * An Internal namespace is private to its package: only code living under the same
 * prefix may depend on it. `Testo\Retry\Internal\*` is reachable from anywhere in
 * `Testo\Retry\`, but not from another plugin, a bridge, or Core. The owning package
 * is the namespace prefix in front of the `Internal` segment, so the boundary tracks
 * the folder layout without being spelled out package by package.
 */
final readonly class InternalIsUsableOnlyWithinItsPackageRule implements RuleInterface, MultipleRuleViolationInterface
{
    private const SEGMENT = '\\Internal\\';

    public function __construct(
        private string $layer,
    ) {}

    public function appliesTo(ClassNode $classNode): bool
    {
        return $classNode->isInLayer($this->layer);
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

        foreach ($classNode->dependencies as $dependency) {
            $position = \strpos($dependency, self::SEGMENT);

            if ($position === false) {
                continue;
            }

            $owner = \substr($dependency, 0, $position);

            if (\str_starts_with($classNode->className, $owner . '\\')) {
                continue;
            }

            $violations[] = new RuleViolation(
                message: \sprintf(
                    '%s [%s] must not use internal [%s] — it is private to package [%s]',
                    $classNode->getType(),
                    $classNode->className,
                    $dependency,
                    $owner,
                ),
                file: $classNode->file,
                line: $classNode->line,
                className: $classNode->className,
                layer: $classNode->layer,
            );
        }

        return $violations;
    }
}
