<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

final class MergePolicyChild extends MergePolicyBase
{
    #[AnotherMarkerAttribute]
    public function methodWithAttrOnBothLayers(): void {}

    public function methodWithAttrOnParentOnly(): void {}

    #[MarkerAttribute]
    public function methodWithAttrOnChildOnly(): void {}

    public function methodWithNoAttr(): void {}

    /**
     * Two repeatable attributes in a single (own) layer, used to exercise the
     * "layer exceeds limit → array_slice" branches of fetchFunctionAttributes.
     */
    #[ReflectionClassAttribute('first')]
    #[ReflectionClassAttribute('second')]
    public function methodWithTwoOwnAttributes(): void {}
}
