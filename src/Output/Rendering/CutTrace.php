<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

use Testo\Lifecycle\Internal\LifecycleAttribute;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Indicates that the method should cut the stack trace of exceptions thrown within it.
 * This is useful for test methods to avoid showing internal framework calls in the stack trace.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class CutTrace implements Interceptable, LifecycleAttribute {}
