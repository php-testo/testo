<?php

declare(strict_types=1);

namespace Testo\Fiber\Exception;

/**
 * Thrown into a pending coroutine's fiber when its scope is torn down — the test body failed, so the
 * coroutine will not be driven any further.
 *
 * Raised at the coroutine's current suspension point, so `finally` blocks run as the fiber unwinds.
 * Don't swallow it: a coroutine that catches the cancellation and suspends again is resumed until it
 * terminates, but it has no schedule to cooperate with anymore.
 *
 * Also rethrown by {@see \Testo\Fiber\Coroutine::await()} on a cancelled coroutine — it has no
 * result to report.
 *
 * @api
 */
final class CancelledException extends \RuntimeException {}
