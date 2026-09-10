<?php

declare(strict_types=1);

namespace Testo\Testing\Internal\Collector;

use Testo\Pipeline\Interceptor;
use Testo\Pipeline\InterceptorCollector;

/**
 * An {@see InterceptorCollector} that records what a plugin adds instead of wiring it into a pipeline.
 *
 * Entries keep the exact argument the plugin passed — an instance when it built one, a class-string when
 * it left construction to the container — so a test can assert on either form.
 *
 * @internal
 * @psalm-internal Testo\Testing
 */
final class RecordingInterceptorCollector implements InterceptorCollector
{
    /** @var list<Interceptor|class-string<Interceptor>> */
    public array $interceptors = [];

    #[\Override]
    public function addInterceptor(Interceptor|string $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }
}
