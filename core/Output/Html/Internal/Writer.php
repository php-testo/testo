<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Internal\Path;

/**
 * Writes the report to disk, in either layout, and answers with its entry file.
 *
 * Both layouts have to open over `file://` with no server, which forbids more than fetching: XHR on
 * local files, ES modules, dynamic `import()` and workers are all unavailable, because the origin is
 * `null`. So the assets are a classic script and a plain stylesheet with relative paths, and the data
 * arrives as a script assigning a global rather than as a fetched `.json`. The single-file layout removes
 * every one of those constraints by having nothing to load at all.
 *
 * One template serves both: the writer substitutes either links to the assets or the assets themselves.
 * Keeping two templates in step by hand is how a report ends up working in one layout only.
 *
 * @internal
 */
final readonly class Writer
{
    private const STYLES_PLACEHOLDER = '{{styles}}';
    private const SCRIPTS_PLACEHOLDER = '{{scripts}}';

    private string $resources;

    public function __construct(?string $resources = null)
    {
        $this->resources = $resources ?? \dirname(__DIR__) . '/resources';
    }

    /**
     * The file a consumer opens for a report written to this destination.
     *
     * Knowable before anything is written, which is what lets the write be announced up front: a
     * `.html` destination is the entry file itself, any other is a directory with `index.html` in it.
     */
    public static function entryFile(Path $destination): Path
    {
        return $destination->extension() === 'html' ? $destination : $destination->join('index.html');
    }

    /**
     * Writes the report in whichever layout the destination asks for.
     *
     * @param Path $destination A `.html` file to fill, or a directory to fill.
     * @param array<non-empty-string, mixed> $document
     * @return Path The entry file — what a consumer opens, and what the announcement points at.
     */
    public function writeHtml(Path $destination, array $document): Path
    {
        return $destination->extension() === 'html'
            ? $this->writeFile($destination, $document)
            : $this->writeDirectory($destination, $document);
    }

    /**
     * Writes the document on its own — the data is a supported artifact in its own right, not only the
     * input of a page.
     *
     * @param array<non-empty-string, mixed> $document
     */
    public function writeData(Path $file, array $document): Path
    {
        self::ensureDirectory($file->parent());
        self::put($file, Json::artifact($document));

        return $file;
    }

    private static function ensureDirectory(Path $directory): void
    {
        $path = (string) $directory;

        \is_dir($path) || \mkdir($path, 0o755, true) || \is_dir($path) or throw new \RuntimeException(
            "Unable to create the report directory {$path}.",
        );
    }

    private static function put(Path $file, string $content): void
    {
        \file_put_contents((string) $file, $content) === false and throw new \RuntimeException(
            'Unable to write the report file ' . (string) $file . '.',
        );
    }

    /**
     * Writes `index.html` plus its assets under the given directory.
     *
     * @param Path $directory Directory to fill; created when missing.
     * @param array<non-empty-string, mixed> $document
     * @return Path The entry file — what a consumer opens, and what the announcement points at.
     */
    private function writeDirectory(Path $directory, array $document): Path
    {
        $assets = $directory->join('assets');
        self::ensureDirectory($assets);

        self::put($assets->join('report.css'), $this->resource('assets/report.css'));
        self::put($assets->join('report.js'), $this->resource('assets/report.js'));
        self::put($assets->join('data.js'), Json::script($document));

        $entry = $directory->join('index.html');
        self::put($entry, \str_replace(
            [self::STYLES_PLACEHOLDER, self::SCRIPTS_PLACEHOLDER],
            [
                '<link rel="stylesheet" href="assets/report.css">',
                '<script src="assets/data.js"></script>' . "\n" . '<script src="assets/report.js"></script>',
            ],
            $this->resource('index.html'),
        ));

        return $entry;
    }

    /**
     * Writes the whole report as one document with everything inlined.
     *
     * @param Path $file Target file; its parent directory is created when missing.
     * @param array<non-empty-string, mixed> $document
     * @return Path The file itself, which is also the entry file.
     */
    private function writeFile(Path $file, array $document): Path
    {
        self::ensureDirectory($file->parent());

        self::put($file, \str_replace(
            [self::STYLES_PLACEHOLDER, self::SCRIPTS_PLACEHOLDER],
            [
                '<style>' . "\n" . $this->resource('assets/report.css') . '</style>',
                '<script>' . "\n" . Json::script($document) . '</script>' . "\n"
                . '<script>' . "\n" . $this->resource('assets/report.js') . '</script>',
            ],
            $this->resource('index.html'),
        ));

        return $file;
    }

    private function resource(string $name): string
    {
        $path = $this->resources . '/' . $name;
        $content = \file_get_contents($path);

        $content === false and throw new \RuntimeException("Unable to read the report asset {$path}.");

        return $content;
    }
}
