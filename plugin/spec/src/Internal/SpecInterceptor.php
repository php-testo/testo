<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Level;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Spec;

/**
 * Publishes a test's {@see Spec} fragment into the messenger channel.
 *
 * The fragment is logged both as ready-to-read Markdown ({@see Message::$content}) and as structured
 * data ({@see Message::$context}) so downstream consumers — the console, a report, or
 * {@see SpecCollector} — can either print it verbatim or rebuild a numbered document from the raw
 * fields without parsing Markdown back.
 *
 * Heading/number metadata comes from {@see \Testo\Spec\SpecHeader}, read here via {@see SpecHeaderReader}:
 * the class-level attribute (the section) and the method-level one (an item override). The final
 * numbers depend on the item's siblings, so they are assigned later by {@see SpecCollector}; this
 * interceptor only forwards the raw section/item titles, the optional manual numbers and the source
 * line used for ordering. A null title means "no explicit header" — the consumer decides the fallback.
 *
 * The only dependency is the {@see Messenger}, so the interceptor works on its own even when
 * {@see \Testo\Spec\SpecPlugin} is not registered: the spec still reaches the channel, only the
 * file generation and reordering are skipped.
 *
 * @see Spec
 *
 * @api
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT, onConflict: ConflictPolicy::Last)]
final readonly class SpecInterceptor implements TestRunInterceptor
{
    /**
     * Channel the rendered specification fragments are written to.
     *
     * @var non-empty-string
     */
    public const CHANNEL = 'spec.md';

    public function __construct(
        private Spec $options,
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $item = SpecHeaderReader::item($info->testDefinition->reflection);
        $section = SpecHeaderReader::section($info->caseInfo->definition->reflection);

        $this->messenger->log(
            self::CHANNEL,
            $this->render($item?->title ?? $info->name, $item?->number),
            Level::Info,
            [
                'title' => $item?->title,
                'story' => $this->options->story,
                'tags' => $this->options->tags,
                'number' => $item?->number,
                'sectionTitle' => $section?->title,
                'sectionNumber' => $section?->number,
                'line' => $info->testDefinition->reflection->getStartLine() ?: 0,
                'test' => $info->name,
                'case' => $info->caseInfo->name,
            ],
        );

        return $next($info);
    }

    /**
     * Render the fragment as a self-contained Markdown section. The final number is the collector's
     * job; a manual item number is shown here, otherwise just the title.
     */
    private function render(string $title, ?string $number): string
    {
        $heading = $number === null ? $title : "{$number} {$title}";
        $out = "### {$heading}\n\n" . \trim($this->options->story) . "\n";

        if ($this->options->tags !== []) {
            $tags = \implode(' ', \array_map(
                static fn(string $tag): string => '`' . $tag . '`',
                $this->options->tags,
            ));
            $out .= "\n_Tags: {$tags}_\n";
        }

        return $out;
    }
}
