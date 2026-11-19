# Interceptors that own the test's fiber

An interceptor that hands the test body to an event loop, a coroutine scope or a fiber scheduler owns
the fiber the test runs in: the loop resumes that fiber itself, directly. Such an interceptor belongs in
one slot only, `InterceptorOptions::ORDER_ASYNC_COROUTINE`, the innermost one. Both shipped async
interceptors sit there: `RunInRevoltInterceptor` (`testo/bridge-revolt`) and `CoroutineScopeInterceptor`
(`testo/fiber`). If the loop is Revolt, use `#[RunInRevolt]` from `testo/bridge-revolt` instead of
writing your own.

## Why the slot decides correctness

Per-test state in Testo is held by **fiber-aware guards**: the assertion collector
(`ORDER_ASSERTIONS - 10`), the messenger scope (`Messenger::scope()`), the error-handler plugin
(`ORDER_CLOSE_TO_TEST`), the coverage window (`ORDER_COVERAGE`). Each one, when the test is inside a
fiber, relays every suspension outward and steps aside while the test is parked: the assertion and
messenger state is swapped out, and the error handler takes itself off the stack (`HandlerScope::suspend()`)
until the fiber is resumed. That is what keeps interleaved tests apart.

A loop interceptor at the default order sits **outer** to those guards. When the test suspends, the
suspension travels out through them, they step aside, and the loop then runs the test's spawned tasks
outside the test's context. Observed effect: a PHP warning raised in a spawned task is not captured, and
the test passes even under `ErrorHandlerPlugin(failOnError: true)`. The same interceptor at
`ORDER_ASYNC_COROUTINE` runs the loop **inside** every guard: the guards open their scopes before the loop
starts and never see a suspension, so the loop and every task it spawns run with the test's state in place,
and the warning is captured and fails the test.

The contract from the `ORDER_ASYNC_COROUTINE` docblock: nothing that suspends may be scheduled outer to
such an interceptor and inner to `ORDER_COVERAGE`. A suspension relayed out of a fiber the interceptor
does not drive is never resumed; with Revolt the run dies on "Event loop terminated without resuming the
current suspension". Keep anything that suspends (a guard of your own, a trampoline) at or below
`ORDER_COVERAGE`.

## Shape

Run the loop inside `runTest()` and call `$next($info)` from within it, once, as the loop's task:

```php
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

#[InterceptorOptions(order: InterceptorOptions::ORDER_ASYNC_COROUTINE)]
final readonly class RunOnLoopInterceptor implements TestRunInterceptor
{
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // MyLoop::run() starts the closure in a fiber of its own, runs the tasks it spawns while it
        // is suspended, and returns once that fiber terminates.
        return MyLoop::run(static fn(): TestResult => $next($info));
    }
}
```

From the outside the test stays an ordinary blocking call; only the body and what it spawns run on the
loop. Data providers, retries and the guards run outside it, and each dataset or retry attempt is a loop
run of its own. Run one test per loop run: the loop resumes its fibers past the guards, so two tests sharing
a run would bleed state into each other.
