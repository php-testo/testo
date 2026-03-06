<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Core\Value\Status;

/**
 * Data transfer object for formatted output items.
 *
 * @internal
 */
final class FormattedItem
{
    public function __construct(
        /**
         * @var non-empty-string
         */
        public readonly string $name,
        public readonly Status $status,
        /**
         * @var int<0, max>|null Duration in milliseconds
         */
        public readonly ?int $duration = null,
        /**
         * @var int<0, max> Indentation level (0 = no indent)
         */
        public readonly int $indentLevel = 0,
        /**
         * @var int<1, max>|null Index in collection (for numbered items)
         */
        public readonly ?int $index = null,
        /**
         * @var non-empty-string|null Additional description (e.g., data provider key)
         */
        public readonly string $description = '',
    ) {}
}
