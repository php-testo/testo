<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestResult;
use Testo\Event\TestSuite\TestSuiteFinished;

/**
 * Renders the collected {@see SpecInterceptor::CHANNEL} fragments into a single ordered Markdown document.
 *
 * Driven by {@see TestSuiteFinished} rather than the session-level finish: that event fires *inside*
 * each suite's container scope, so the collector works whether the plugin is registered application-
 * wide or for a single suite (a session-scoped listener would never reach a suite-scoped plugin).
 * Fragments accumulate across suites and the document is rewritten on every suite finish, so the
 * final file always reflects the whole run.
 *
 * Numbered sections come first (via {@see SpecNumberer}); fragments with no section number form an
 * "Uncategorized" tail at the end — each unnumbered case is a visually separated sub-block, rendered
 * as a bullet list when items carry a header, or as plain paragraphs otherwise.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final class SpecCollector
{
    /** @var non-empty-string */
    private const FILENAME = 'spec.md';

    /** @var list<SpecEntry> */
    private array $entries = [];

    /**
     * @param non-empty-string $outputDir Directory the Markdown document is written to.
     */
    public function __construct(
        private readonly string $outputDir,
    ) {}

    public function onTestSuiteFinished(TestSuiteFinished $event): void
    {
        $this->addSuite($event->suiteResult);
        $this->flush();
    }

    /**
     * Accumulate every spec fragment recorded in the given suite, in execution order.
     */
    public function addSuite(SuiteResult $suite): void
    {
        foreach ($suite->results as $case) {
            \assert($case instanceof CaseResult);
            foreach ($case->results as $test) {
                \assert($test instanceof TestResult);
                foreach ($test->messages->channel(SpecInterceptor::CHANNEL) as $message) {
                    $entry = SpecEntry::fromMessage($message);
                    $entry === null or $this->entries[] = $entry;
                }
            }
        }
    }

    /**
     * Render everything accumulated so far into the document file. Returns the written path, or null
     * when nothing has been collected.
     *
     * @return non-empty-string|null
     */
    public function flush(): ?string
    {
        if ($this->entries === []) {
            return null;
        }

        \is_dir($this->outputDir) || \mkdir($this->outputDir, 0o775, recursive: true);

        $path = $this->outputDir . \DIRECTORY_SEPARATOR . self::FILENAME;
        \file_put_contents($path, $this->render(SpecNumberer::build($this->entries)));

        return $path;
    }

    /**
     * @param array{sections: list<array{number: non-empty-string, title: non-empty-string, items: list<array{number: non-empty-string, title: non-empty-string, story: string, tags: list<non-empty-string>}>}>, extra: list<array{title: non-empty-string|null, items: list<array{title: non-empty-string|null, story: string, tags: list<non-empty-string>}>}>} $model
     */
    public function render(array $model): string
    {
        $blocks = [];

        foreach ($model['sections'] as $section) {
            $out = "# {$section['number']}. {$section['title']}\n";
            foreach ($section['items'] as $item) {
                $out .= "\n## {$item['number']} {$item['title']}\n\n" . \trim($item['story']) . "\n";
                $out .= self::tags($item['tags'], '');
            }
            $blocks[] = $out;
        }

        if ($model['extra'] !== []) {
            $blocks[] = $this->renderExtra($model['extra']);
        }

        return \implode("\n", $blocks);
    }

    /**
     * @param list<non-empty-string> $tags
     */
    private static function tags(array $tags, string $indent): string
    {
        if ($tags === []) {
            return '';
        }

        $rendered = \implode(' ', \array_map(static fn(string $t): string => '`' . $t . '`', $tags));

        return "\n{$indent}_Tags: {$rendered}_\n";
    }

    private static function indent(string $text): string
    {
        return \implode("\n", \array_map(
            static fn(string $line): string => $line === '' ? '' : '  ' . $line,
            \explode("\n", $text),
        ));
    }

    /**
     * @param list<array{title: non-empty-string|null, items: list<array{title: non-empty-string|null, story: string, tags: list<non-empty-string>}>}> $extra
     */
    private function renderExtra(array $extra): string
    {
        $out = "# Uncategorized\n";
        foreach ($extra as $block) {
            $out .= "\n";
            $block['title'] === null or $out .= "## {$block['title']}\n\n";

            foreach ($block['items'] as $item) {
                if ($item['title'] !== null) {
                    // Has a header → bullet list item with the story indented under it.
                    $out .= "- {$item['title']}\n" . self::indent(\trim($item['story'])) . "\n";
                    $out .= self::tags($item['tags'], '  ');
                } else {
                    // No header → a plain paragraph.
                    $out .= \trim($item['story']) . "\n";
                    $out .= self::tags($item['tags'], '');
                }
            }
        }

        return $out;
    }
}
