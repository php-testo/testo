<?php

declare(strict_types=1);

namespace Testo\Core\Metadata;

/**
 * The values a test reports beside its status, in the order they were added.
 *
 * Every reporter renders the same collection in place: TeamCity as `testMetadata`, JUnit as
 * `<property>`, the JSON and HTML reports as data. Names may repeat; nothing is merged.
 *
 * @implements \IteratorAggregate<int, MetadataEntry>
 * @api
 */
final readonly class Metadata implements \IteratorAggregate, \Countable
{
    /**
     * @param list<MetadataEntry> $entries
     */
    public function __construct(
        public array $entries = [],
    ) {}

    public function with(MetadataEntry ...$entries): self
    {
        return $entries === [] ? $this : new self([...$this->entries, ...\array_values($entries)]);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    #[\Override]
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->entries);
    }
}
