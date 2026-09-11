<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Preset;

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\PresetInterface;
use Boundwize\StructArmed\Rule\Rules\Composer\Psr4NamespaceRule;
use Testo\Tools\StructArmed\Rule\ClassMustDeclareApiOrInternalRule;
use Testo\Tools\StructArmed\Rule\InternalClassMustBeFinalRule;
use Testo\Tools\StructArmed\Rule\InternalNamespaceMustBeAnnotatedInternalRule;

/**
 * One layer, one preset: binds a named layer to its source paths and applies the
 * shared rule set to it. Psr4NamespaceRule resolves each file against the nearest
 * composer.json, so the check works for the root package and the plugin/bridge
 * path packages alike, without their namespaces being mirrored into the root.
 */
abstract readonly class AbstractLayerPreset implements PresetInterface
{
    public function apply(Architecture $architecture): void
    {
        $layer = $this->layer();
        $prefix = \strtolower($layer);

        $architecture->layer($layer, $this->sourcePaths());
        $architecture->rule("{$prefix}.psr4.classes_must_match_composer", new Psr4NamespaceRule($layer));
        $architecture->rule("{$prefix}.class_must_declare_api_or_internal", new ClassMustDeclareApiOrInternalRule($layer));
        $architecture->rule("{$prefix}.internal_namespace_must_be_annotated_internal", new InternalNamespaceMustBeAnnotatedInternalRule($layer));
        $architecture->rule("{$prefix}.internal_class_must_be_final", new InternalClassMustBeFinalRule($layer));
    }

    abstract protected function layer(): string;

    /**
     * @return list<string> paths resolved against the project root
     */
    abstract protected function sourcePaths(): array;

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
