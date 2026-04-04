<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

abstract class MergePolicyBase
{
    #[MarkerAttribute]
    public function methodWithAttrOnBothLayers(): void {}

    #[MarkerAttribute]
    public function methodWithAttrOnParentOnly(): void {}

    public function methodWithAttrOnChildOnly(): void {}

    public function methodWithNoAttr(): void {}
}
