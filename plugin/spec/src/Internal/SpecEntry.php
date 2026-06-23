<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Core\Log\Message;

/**
 * One collected specification fragment, rebuilt from a {@see SpecInterceptor::CHANNEL} message.
 *
 * Carries the raw, pre-numbering data: the item content plus the section it belongs to and the
 * source line used to order items within that section. {@see SpecNumberer} turns these into the
 * final `{section}.{n}` numbers.
 *
 * `title` and `sectionTitle` are null when no explicit {@see \Testo\Spec\SpecHeader} was given; consumers
 * fall back to {@see $test} / {@see $case} for a numbered heading, or treat the absence as "render
 * as a plain paragraph" in the unnumbered block.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final readonly class SpecEntry
{
    /**
     * @param non-empty-string $case Test Case the fragment belongs to (groups fragments into a section).
     * @param non-empty-string $test Test the fragment is attached to (heading fallback).
     * @param non-empty-string|null $title Explicit item heading, or null when no header was given.
     * @param non-empty-string|null $number Manual item number override, or null for auto-numbering.
     * @param non-empty-string|null $sectionTitle Explicit section heading, or null.
     * @param non-empty-string|null $sectionNumber Manual section number, or null for "unnumbered".
     * @param int $line Source line of the test, used to order items within a section.
     * @param list<non-empty-string> $tags
     */
    public function __construct(
        public string $case,
        public string $test,
        public ?string $title,
        public ?string $number,
        public ?string $sectionTitle,
        public ?string $sectionNumber,
        public int $line,
        public string $story,
        public array $tags,
    ) {}

    /**
     * Rebuild an entry from a channel message, or null when the message carries no spec context
     * (e.g. it was written to the channel by something other than {@see SpecInterceptor}).
     */
    public static function fromMessage(Message $message): ?self
    {
        $context = $message->context;
        $story = $context['story'] ?? null;
        $case = $context['case'] ?? null;
        $test = $context['test'] ?? null;

        if (!\is_string($story) || !\is_string($case) || $case === '' || !\is_string($test) || $test === '') {
            return null;
        }

        /** @var list<non-empty-string> $tags */
        $tags = \is_array($context['tags'] ?? null)
            ? \array_values(\array_filter($context['tags'], static fn(mixed $t): bool => \is_string($t) && $t !== ''))
            : [];

        return new self(
            case: $case,
            test: $test,
            title: self::nonEmptyString($context['title'] ?? null),
            number: self::nonEmptyString($context['number'] ?? null),
            sectionTitle: self::nonEmptyString($context['sectionTitle'] ?? null),
            sectionNumber: self::nonEmptyString($context['sectionNumber'] ?? null),
            line: \is_int($context['line'] ?? null) ? $context['line'] : 0,
            story: $story,
            tags: $tags,
        );
    }

    /**
     * Heading to show for this item: the explicit title or, failing that, the test name.
     *
     * @return non-empty-string
     */
    public function heading(): string
    {
        return $this->title ?? $this->test;
    }

    /**
     * @return non-empty-string|null
     */
    private static function nonEmptyString(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
