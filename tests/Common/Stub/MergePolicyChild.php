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
}
