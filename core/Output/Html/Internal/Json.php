<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

/**
 * Encodes the report document, in the two forms it is consumed in.
 *
 * Both are deterministic: the document arrives with its order already fixed, and nothing here sorts,
 * stamps or reorders anything. Floats keep their fraction so a duration of `1.0` does not turn into `1`
 * and back, which would make two encodings of one run differ.
 *
 * Invalid UTF-8 is substituted rather than fatal. Message contents are raw bytes from user code — binary
 * output, a non-UTF-8 fixture — and a run that produced one bad byte must still get its report.
 *
 * @internal
 */
final class Json
{
    private const COMMON = \JSON_UNESCAPED_UNICODE
        | \JSON_INVALID_UTF8_SUBSTITUTE
        | \JSON_PRESERVE_ZERO_FRACTION
        | \JSON_THROW_ON_ERROR;

    private function __construct() {}

    /**
     * The standalone data artifact: pretty-printed and with readable slashes, because a human and a
     * `git diff` are among its consumers.
     *
     * @param array<non-empty-string, mixed> $document
     */
    public static function artifact(array $document): string
    {
        return \json_encode($document, self::COMMON | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * The same document as a script that assigns a global.
     *
     * Compact, and with slashes escaped on purpose: a message containing `</script>` would otherwise
     * close the tag it is embedded in and hand the rest of the report to the HTML parser. `<\/script>`
     * is the same string to a JSON reader and inert to an HTML one.
     *
     * @param array<non-empty-string, mixed> $document
     */
    public static function script(array $document): string
    {
        return 'window.TESTO_REPORT = ' . \json_encode($document, self::COMMON) . ";\n";
    }
}
