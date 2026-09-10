<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Stub\Plugin;

use Testo\Pipeline\Interceptor;

/**
 * An interceptor the stub plugin never adds — the target for asserting that a missing-interceptor check
 * fails.
 */
final class UnusedStubInterceptor implements Interceptor {}
