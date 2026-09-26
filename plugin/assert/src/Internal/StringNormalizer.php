<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

/**
 * The comparison modes of the string assertions and the normalization they apply,
 * to the subject and to every check argument alike.
 *
 * The steps run in a fixed order whatever order the modes were enabled in:
 * ANSI codes, line endings, whitespace, blank lines, case.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class StringNormalizer
{
    /**
     * 7-bit ANSI/VT escape sequences: OSC ended by BEL or ST, CSI, charset designation and the other
     * two-byte escapes (`ESC 7`/`ESC 8` save and restore the cursor, `ESC M`, `ESC c`, …). The 8-bit
     * C1 forms are left alone, their bytes also occur inside UTF-8.
     */
    private const ANSI = '/\e(?:\][^\x07\e]*(?:\x07|\e\\\\)|\[[0-?]*[ -\/]*[@-~]|[()*+][ -\/]*[0-~]|[0-Z\\\\-~])/';

    /**
     * @param bool $ignoreWhitespace Collapse horizontal whitespace and trim every line.
     * @param bool $acrossLineBreaks With {@see $ignoreWhitespace}, line breaks count as whitespace too
     *        and the text becomes a single trimmed line.
     */
    public function __construct(
        public bool $ignoreCase = false,
        public bool $ignoreLineEndings = false,
        public bool $ignoreWhitespace = false,
        public bool $acrossLineBreaks = false,
        public bool $ignoreBlankLines = false,
        public bool $ignoreAnsi = false,
    ) {}

    public function withIgnoreCase(): self
    {
        return new self(
            true,
            $this->ignoreLineEndings,
            $this->ignoreWhitespace,
            $this->acrossLineBreaks,
            $this->ignoreBlankLines,
            $this->ignoreAnsi,
        );
    }

    public function withIgnoreLineEndings(): self
    {
        return new self(
            $this->ignoreCase,
            true,
            $this->ignoreWhitespace,
            $this->acrossLineBreaks,
            $this->ignoreBlankLines,
            $this->ignoreAnsi,
        );
    }

    public function withIgnoreWhitespace(bool $acrossLineBreaks): self
    {
        return new self(
            $this->ignoreCase,
            $this->ignoreLineEndings,
            true,
            $acrossLineBreaks,
            $this->ignoreBlankLines,
            $this->ignoreAnsi,
        );
    }

    public function withIgnoreBlankLines(): self
    {
        return new self(
            $this->ignoreCase,
            $this->ignoreLineEndings,
            $this->ignoreWhitespace,
            $this->acrossLineBreaks,
            true,
            $this->ignoreAnsi,
        );
    }

    public function withIgnoreAnsi(): self
    {
        return new self(
            $this->ignoreCase,
            $this->ignoreLineEndings,
            $this->ignoreWhitespace,
            $this->acrossLineBreaks,
            $this->ignoreBlankLines,
            true,
        );
    }

    /**
     * Apply every active mode.
     *
     * @param bool $withCase Whether to fold the case too; regex checks run on the unfolded text.
     */
    public function normalize(string $value, bool $withCase = true): string
    {
        $this->ignoreAnsi and $value = (string) \preg_replace(self::ANSI, '', $value);

        if ($this->ignoreLineEndings || $this->ignoreWhitespace || $this->ignoreBlankLines) {
            $value = \strtr($value, ["\r\n" => "\n", "\r" => "\n"]);
        }

        # The Unicode classes need valid UTF-8; other input falls back to the ASCII ones.
        $utf8 = \mb_check_encoding($value, 'UTF-8');

        if ($this->ignoreWhitespace) {
            $value = $this->acrossLineBreaks
                ? \trim((string) \preg_replace($utf8 ? '/[\s\h\v]+/u' : '/\s+/', ' ', $value), ' ')
                : \implode("\n", \array_map(
                    static fn(string $line): string => \trim($line, ' '),
                    \explode("\n", (string) \preg_replace($utf8 ? '/\h+/u' : '/[ \t]+/', ' ', $value)),
                ));
        }

        if ($this->ignoreBlankLines) {
            $blank = $utf8 ? '/^[\s\h\v]*$/u' : '/^\s*$/';
            $value = \implode("\n", \array_filter(
                \explode("\n", $value),
                static fn(string $line): bool => \preg_match($blank, $line) !== 1,
            ));
        }

        return $withCase && $this->ignoreCase ? \mb_strtolower($value, 'UTF-8') : $value;
    }

    /**
     * The active modes as a description suffix, e.g. ` (ignoring line endings, ignoring case)`,
     * or an empty string when none is active.
     *
     * @param bool $withCase Whether the case mode applies to the check being described.
     */
    public function describe(bool $withCase = true): string
    {
        $modes = [];
        $this->ignoreAnsi and $modes[] = 'ignoring ANSI codes';
        $this->ignoreLineEndings and $modes[] = 'ignoring line endings';
        $this->ignoreWhitespace and $modes[] = $this->acrossLineBreaks
            ? 'ignoring whitespace across line breaks'
            : 'ignoring whitespace';
        $this->ignoreBlankLines and $modes[] = 'ignoring blank lines';
        $withCase && $this->ignoreCase and $modes[] = 'ignoring case';

        return $modes === [] ? '' : ' (' . \implode(', ', $modes) . ')';
    }
}
