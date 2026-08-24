<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Html;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Event\TestSuite\TestSuiteFinished;

/**
 * Collects every report announcement a run makes, so a test can check what the reporter told the rest of
 * the world rather than only what it left on disk.
 *
 * One closure serves both moments, subscribed once per event: the path is what this records, and only the
 * file events have one. Each entry states whether the file was there when the event arrived — the
 * difference between announcing a promise and announcing a fact — and how many suites had already
 * finished, which is what pins the early announcement inside the run's still-open output.
 */
final class ReportSpyPlugin implements PluginConfigurator
{
    /**
     * @var list<array{
     *     event: ReportFileGenerating|ReportFileGenerated,
     *     existed: bool,
     *     suitesFinished: int
     * }>
     */
    public static array $seen = [];

    private static int $suitesFinished = 0;

    public static function reset(): void
    {
        self::$seen = [];
        self::$suitesFinished = 0;
    }

    /**
     * @return list<ReportFileGenerated>
     */
    public static function generated(): array
    {
        $written = [];
        foreach (self::$seen as $entry) {
            $entry['event'] instanceof ReportFileGenerated and $written[] = $entry['event'];
        }

        return $written;
    }

    /**
     * @return list<class-string<ReportFileGenerating|ReportFileGenerated>>
     */
    public static function sequence(): array
    {
        return \array_map(
            static fn(array $entry): string => $entry['event']::class,
            self::$seen,
        );
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $listeners = $container->get(EventListenerCollector::class);

        $record = static function (ReportFileGenerating|ReportFileGenerated $event): void {
            self::$seen[] = [
                'event' => $event,
                'existed' => \is_file((string) $event->info->path),
                'suitesFinished' => self::$suitesFinished,
            ];
        };

        $listeners->addListener(ReportFileGenerating::class, $record);
        $listeners->addListener(ReportFileGenerated::class, $record);

        $listeners->addListener(
            TestSuiteFinished::class,
            static function (): void {
                ++self::$suitesFinished;
            },
        );
    }
}
