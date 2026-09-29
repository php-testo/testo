<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Test;

/**
 * Self-tests for lifecycle hooks overriding hooks of a parent class.
 */
#[Test]
#[Covers(BeforeTest::class)]
#[Covers(AfterTest::class)]
#[Covers(LifecycleInterceptor::class)]
final class InheritedHooks extends InheritedHooksBase
{
    #[BeforeTest]
    public function setUp(): void
    {
        $this->log[] = 'setUp';
    }

    public function prepare(): void
    {
        $this->log[] = 'prepare';
    }

    #[BeforeTest(priority: 500)]
    public function middle(): void
    {
        $this->log[] = 'middle';
    }

    #[AfterTest]
    public function record(): void
    {
        $this->log[] = 'record';
    }

    /**
     * An overridden hook runs once: the nearest declaration of an attribute sets its priority,
     * an override without the attribute keeps the parent's one, and an attribute of another
     * lifecycle kind on the override adds to the parent's one instead of replacing it.
     */
    public function overriddenHooksRunOnce(): void
    {
        Assert::same($this->log, ['prepare', 'middle', 'setUp', 'record']);
    }
}
