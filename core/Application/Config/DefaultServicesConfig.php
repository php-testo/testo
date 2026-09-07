<?php

declare(strict_types=1);

namespace Testo\Application\Config;

use Internal\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Testo\Application\Internal\EventDispatcher;
use Testo\Application\Internal\MessengerHub;
use Testo\Pipeline\Internal\OutputInterceptor;
use Testo\Common\EventListenerCollector;
use Testo\Common\Messenger;
use Testo\Common\PluginConfigurator;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Event\TestSuite\TestSuiteStarting;
use Testo\Pipeline\InterceptorCollector;
use Testo\Pipeline\InterceptorProvider;

/**
 * Testo default services configuration.
 *
 * @api
 */
final readonly class DefaultServicesConfig implements PluginConfigurator
{
    /**
     * @var array<class-string, null|class-string|array<string, mixed>|\Closure(Container): object>
     */
    public array $services;

    /**
     * @param array<class-string, null|class-string|array<string, mixed>|\Closure(Container): object> $definitions
     *        Bindings (service definitions) for the Container {@see Container::bind()}.
     *        You can override the default services by providing your own implementations here with the same keys.
     */
    public function __construct(
        array $definitions = [],
    ) {
        $this->services = $definitions + self::getDefaults();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        foreach ($this->services as $key => $definition) {
            $container->bind($key, $definition);
        }

        $messenger = $container->get(Messenger::class);
        $container->get(InterceptorCollector::class)->addInterceptor(new OutputInterceptor($messenger));
        $this->captureOutput($container->get(EventListenerCollector::class), $messenger);
    }

    private static function getDefaults(): array
    {
        return [
            # Event Dispatcher
            EventDispatcherInterface::class => EventDispatcher::class,
            ListenerProviderInterface::class => EventDispatcher::class,
            EventListenerCollector::class => EventDispatcher::class,
            InterceptorCollector::class => InterceptorProvider::class,
            Messenger::class => MessengerHub::class,
        ];
    }

    /**
     * Bracket each suite's execution with a process-wide output buffer routed into `stdout`.
     */
    private function captureOutput(EventListenerCollector $events, Messenger $messenger): void
    {
        $stdout = $messenger->channel(Messenger::CHANNEL_STDOUT);

        # Output buffers are a process-wide stack; remember the level we started at so the
        # matching close drains exactly our buffer (and anything a test left open on top of it).
        $baseLevel = 0;

        $events->addListener(
            TestSuiteStarting::class,
            static function () use ($stdout, &$baseLevel): void {
                $baseLevel = \ob_get_level();
                \ob_start(static function (string $buffer) use ($stdout): string {
                    $buffer === '' or $stdout->write($buffer);
                    return '';
                }, 1);
            },
        );

        $events->addListener(
            TestSuiteFinished::class,
            static function () use (&$baseLevel): void {
                while (\ob_get_level() > $baseLevel) {
                    \ob_end_flush();
                }
            },
        );
    }
}
