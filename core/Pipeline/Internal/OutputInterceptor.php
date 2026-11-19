<?php

declare(strict_types=1);

namespace Testo\Pipeline\Internal;

use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Forks the {@see Messenger} per test and adds the messages recorded during the test to its
 * {@see TestResult}, next to any messages an inner interceptor attached to the result itself.
 *
 * The native output buffer itself is opened once per suite by {@see \Testo\Application\Config\DefaultServicesConfig}
 * and funnels everything into the `stdout` channel. This interceptor only opens a {@see Messenger::scope()}
 * around the test so that captured output (and anything other producers write) lands in an isolated,
 * per-test buffer — `scope()` also keeps that buffer correctly bound across fiber suspensions.
 *
 * Ordering: runs as early as possible while still being *inside* the data-provider loop
 * ({@see InterceptorOptions::ORDER_DATA_PROVIDER}), so each individual test (data set) gets its
 * own slice of messages and its own {@see TestResult}. A negative order keeps it as an outer
 * wrapper around assertions and the test body.
 *
 * @internal
 */
#[InterceptorOptions(order: self::ORDER)]
final readonly class OutputInterceptor implements TestRunInterceptor
{
    /**
     * Greater than {@see InterceptorOptions::ORDER_DATA_PROVIDER} (per data set) and less than
     * {@see InterceptorOptions::ORDER_DEFAULT}, so the scope is the outermost per-test wrapper:
     * it encloses every other interceptor (assertions, expectations, …) and the test body, and
     * therefore captures output produced by any of them — including the assertion history other
     * plugins write into a channel after the test returns.
     */
    private const ORDER = InterceptorOptions::ORDER_DATA_PROVIDER + 1;

    public function __construct(
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        # Fork the messenger: everything written during this test lands in an isolated buffer, and
        # every MessageReceived it dispatches is stamped with this test's identity.
        return $this->messenger->scope(static function (Messenger $messenger) use ($info, $next): TestResult {
            $result = $next($info);

            $messages = $messenger->getMessages();

            return $messages->isEmpty()
                ? $result
                : $result->withMessages(self::merge($result->messages, $messages));
        }, $info->identity);
    }

    /**
     * Combines the messages an inner interceptor attached to the result with the ones recorded in
     * the scope, in time order. The sort is stable, so on equal timestamps the attached messages
     * come first.
     */
    private static function merge(MessageLog $attached, MessageLog $recorded): MessageLog
    {
        if ($attached->isEmpty()) {
            return $recorded;
        }

        $merged = [...$attached->all(), ...$recorded->all()];
        \usort($merged, static fn(Message $a, Message $b): int => $a->time <=> $b->time);

        return new MessageLog($merged);
    }
}
