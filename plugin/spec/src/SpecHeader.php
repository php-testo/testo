<?php

declare(strict_types=1);

namespace Testo\Spec;

/**
 * A numbered heading for the generated specification document — the *structure* around a {@see \Testo\Spec}.
 *
 * - On a **class** it opens a section: `#[SpecHeader(number: '5', title: 'Checkout')]` renders as
 *   `# 5. Checkout`, and every {@see \Testo\Spec} method in that case becomes an item under it.
 * - On a **method/function** it overrides that item's heading and/or number.
 *
 * Numbering is hybrid: a section keeps the `number` you give it (so it maps onto an external spec
 * document), while items are auto-numbered `{section}.{n}` in source order. Leave `number` null to
 * let the generator assign it automatically. A method-level `number` pins that single item.
 *
 * Unlike {@see \Testo\Spec} this attribute is plain metadata — it does not run anything on its own and
 * is read from reflection while documents are generated. A `SpecHeader` without a matching `Spec`
 * produces no output.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
final readonly class SpecHeader
{
    /** @var non-empty-string|null */
    public ?string $number;

    /**
     * @param int|string|null $number Manual number. On a class it is the section number (e.g. `5` or
     *        `'A'`); on a method it pins the full item number (e.g. `'5.1'`). Null means auto.
     * @param non-empty-string|null $title Heading text (section title, or an item title overriding the
     *        test name). Null falls back to the case/test name when rendered.
     */
    public function __construct(
        int|string|null $number = null,
        public ?string $title = null,
    ) {
        $title !== null && \trim($title) === '' and throw new \InvalidArgumentException(
            'Spec header title must not be empty when provided.',
        );

        $number = $number === null ? null : (string) $number;
        $number !== null && \trim($number) === '' and throw new \InvalidArgumentException(
            'Spec header number must not be empty when provided.',
        );
        $this->number = $number;

        $this->title === null && $this->number === null and throw new \InvalidArgumentException(
            'Spec header requires a title or a number.',
        );
    }
}
