<?php

declare(strict_types=1);

namespace Testo;

/**
 * Marks a class (public methods), or a method or a function as a test.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
final readonly class Test {}
