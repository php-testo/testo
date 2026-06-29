<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Runner;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Test\TestPipelineStarting;

/**
 * Records every {@see TestPipelineStarting} event the suite dispatches, so a feature test can
 * assert that {@see \Testo\Application\Internal\Runner\TestRunner::runTest()} emits it.
 */
final class PipelineStartingRecorderPlugin implements PluginConfigurator
{
    /** @var list<non-empty-string> */
    public static array $names = [];

    public static function reset(): void
    {
        self::$names = [];
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(EventListenerCollector::class)->addListener(
            TestPipelineStarting::class,
            static function (TestPipelineStarting $event): void {
                self::$names[] = $event->testInfo->name;
            },
        );
    }
}
