<?php

declare(strict_types=1);

namespace Testo\Filter\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;

/**
 * @internal
 * @psalm-internal Testo\Filter
 */
#[InflectableConfig]
final class FilterInput
{
    /**
     * @var non-empty-string[]
     */
    #[InputOption('filter')]
    public array $filter = [];

    /**
     * @var non-empty-string[]
     */
    #[InputOption('path')]
    public array $path = [];

    /**
     * @var non-empty-string[]
     */
    #[InputOption('suite')]
    public array $suite = [];

    /**
     * Raw type filters. A leading `!` marks an exclusion; it is stripped and resolved downstream.
     *
     * @var non-empty-string[]
     */
    #[InputOption('type')]
    public array $type = [];

    /**
     * Raw group filters. A leading `!` marks an exclusion; it is stripped and resolved downstream.
     *
     * @var non-empty-string[]
     */
    #[InputOption('group')]
    public array $group = [];
}
