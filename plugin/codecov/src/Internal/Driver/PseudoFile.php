<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Inline\TestInline;

/**
 * Recognizes the names coverage engines give to code without a file of its own: `eval()`'d code,
 * runtime-created functions, assertion strings, standard input.
 *
 * Such a name starts with the path of the file that produced the code, so a path-prefix filter alone lets it through.
 *
 * @internal
 */
final class PseudoFile
{
    private const MARKERS = [
        'xdebug://debug-eval',
        "eval()'d code",
        'runtime-created function',
        'runkit created function',
        'assert code',
        'regexp code',
        'Standard input code',
    ];

    #[TestInline(["/app/src/Foo.php(64) : eval()'d code"], result: true)]
    #[TestInline(["C:\\app\\src\\Foo.php(3) : eval()'d code"], result: true)]
    #[TestInline(['xdebug://debug-eval'], result: true)]
    #[TestInline(['/app/src/Foo.php(10) : runtime-created function'], result: true)]
    #[TestInline(['Standard input code'], result: true)]
    #[TestInline(['-'], result: true)]
    #[TestInline(['vfs://root/Foo.php'], result: true)]
    #[TestInline(['/app/src/Foo.php'], result: false)]
    #[TestInline(['C:\\app\\src\\Foo.php'], result: false)]
    public static function is(string $path): bool
    {
        if ($path === '-' || \str_starts_with($path, 'vfs://')) {
            return true;
        }

        foreach (self::MARKERS as $marker) {
            if (\str_contains($path, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T
     * @param array<string, T> $data Raw coverage keyed by file path.
     * @return array<string, T>
     */
    public static function remove(array $data): array
    {
        return \array_filter($data, static fn(string $path): bool => !self::is($path), \ARRAY_FILTER_USE_KEY);
    }
}
