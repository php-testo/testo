<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Skipped;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Event\Test\TestStarting;
use Testo\Pipeline\InterceptorCollector;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Flags {@see SkippedByLocator::flagged()} as skipped at location time, the way a plugin that knows
 * ahead of the run does, and records every {@see TestStarting} the suite dispatches: a skipped
 * definition has no body to announce.
 */
final class FlagSkippedPlugin implements PluginConfigurator
{
    /** @var list<non-empty-string> */
    public static array $started = [];

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(
            new class implements CaseLocatorInterceptor {
                #[\Override]
                public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
                {
                    /** @var CaseDefinitions $result */
                    $result = $next($file);
                    foreach ($result->getCases() as $case) {
                        foreach ($case->tests->getTests() as $name => $test) {
                            $name === 'flagged' and $test->skipped = true;
                        }
                    }

                    return $result;
                }
            },
        );

        $container->get(EventListenerCollector::class)->addListener(
            TestStarting::class,
            static function (TestStarting $event): void {
                self::$started[] = $event->testInfo->name;
            },
        );
    }
}
