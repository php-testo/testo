<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\PresetInterface;
use Boundwize\StructArmed\Rule\Rules\Composer\Psr4NamespaceRule;
use Boundwize\StructArmed\Rule\Rules\Layer\MayNotDependOnRule;
use Testo\Tools\StructArmed\Rule\ClassMustDeclareApiOrInternalRule;
use Testo\Tools\StructArmed\Rule\InternalClassMustBeFinalRule;
use Testo\Tools\StructArmed\Rule\InternalIsUsableOnlyWithinItsPackageRule;
use Testo\Tools\StructArmed\Rule\InternalMustNotLeakIntoPublicSignatureRule;
use Testo\Tools\StructArmed\Rule\InternalNamespaceMustBeAnnotatedInternalRule;

/**
 * One layer, one preset: binds a named layer to its source paths and applies the
 * shared rule set to it. Psr4NamespaceRule resolves each file against the nearest
 * composer.json, so the check works for the root package and the plugin/bridge
 * path packages alike, without their namespaces being mirrored into the root.
 *
 * A layer owns two kinds of boundary. Encapsulation is uniform, so it lives in the
 * shared set: an Internal namespace stays private to its package, and no public
 * surface leaks an internal type into its signature. Direction is per layer, so a
 * concrete preset lists the layers it may not depend on via mayNotDependOn().
 *
 * Each preset also folds its paths into the synthetic `Source` layer. Defining
 * `Source` ourselves stops StructArmed from synthesising it from the root
 * composer.json — which would drag tests/ and sibling packages into the scan — so
 * the analysis sees exactly these src trees and nothing else.
 */
abstract readonly class AbstractLayerPreset implements PresetInterface
{
    public function apply(Architecture $architecture): void
    {
        $layer = $this->layer();
        $prefix = \strtolower($layer);
        $paths = $this->sourcePaths();

        $architecture->layer($layer, $paths);
        $architecture->layer('Source', [...($architecture->getLayers()['Source'] ?? []), ...$paths]);
        $architecture->rule("{$prefix}.psr4.classes_must_match_composer", new Psr4NamespaceRule($layer));
        $architecture->rule("{$prefix}.class_must_declare_api_or_internal", new ClassMustDeclareApiOrInternalRule($layer));
        $architecture->rule("{$prefix}.internal_namespace_must_be_annotated_internal", new InternalNamespaceMustBeAnnotatedInternalRule($layer));
        $architecture->rule("{$prefix}.internal_class_must_be_final", new InternalClassMustBeFinalRule($layer));
        $architecture->rule("{$prefix}.internal_is_usable_only_within_its_package", new InternalIsUsableOnlyWithinItsPackageRule($layer));
        $architecture->rule("{$prefix}.internal_must_not_leak_into_public_signature", new InternalMustNotLeakIntoPublicSignatureRule($layer));

        foreach ($this->mayNotDependOn() as $target) {
            $architecture->rule("{$prefix}.must_not_depend_on_" . \strtolower($target), new MayNotDependOnRule($layer, $target));
        }
    }

    abstract protected function layer(): string;

    /**
     * @return list<string> paths resolved against the project root
     */
    abstract protected function sourcePaths(): array;

    /**
     * Layers this one must not depend on. Empty by default; a layer that sits below
     * others overrides this to pin the allowed direction of dependencies.
     *
     * @return list<string>
     */
    protected function mayNotDependOn(): array
    {
        return [];
    }

    /**
     * Directories matching a glob pattern, relative to the project root (analyse's
     * working directory). Lets a layer enumerate its packages instead of listing
     * every path by hand.
     *
     * @return list<string>
     */
    final protected function glob(string $pattern): array
    {
        $dirs = \glob($pattern, \GLOB_ONLYDIR) ?: [];
        \sort($dirs);

        return \array_values($dirs);
    }
}
