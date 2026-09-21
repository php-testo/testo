<?php

declare(strict_types=1);

/**
 * Slide 5. Memory leaks: `Expect::notLeaks()` vs `WeakReference` plumbing by hand.
 */

namespace Sample\PhpUnit {

    use App\Events\EventDispatcher;
    use App\Events\OrderPlacedListener;
    use PHPUnit\Framework\TestCase;

    final class EventDispatcherTest extends TestCase
    {
        public function testReleasesListenerAfterRemoval(): void
        {
            $dispatcher = new EventDispatcher();
            $listener = new OrderPlacedListener();
            $dispatcher->listen('order.placed', $listener);

            $dispatcher->forget('order.placed', $listener);

            $ref = \WeakReference::create($listener);
            unset($listener);
            \gc_collect_cycles();
            $this->assertNull($ref->get(), 'Listener is still referenced by the dispatcher');
        }
    }
}

namespace Sample\Testo {

    use App\Events\EventDispatcher;
    use App\Events\OrderPlacedListener;
    use Testo\Expect;
    use Testo\Test;

    #[Test]
    final class EventDispatcherTest
    {
        public function releasesListenerAfterRemoval(): void
        {
            $dispatcher = new EventDispatcher();
            $listener = new OrderPlacedListener();
            $dispatcher->listen('order.placed', $listener);
            Expect::notLeaks($listener);

            $dispatcher->forget('order.placed', $listener);
        }
    }
}
