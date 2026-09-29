<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Self;

use Testo\Lifecycle\BeforeTest;

/**
 * Parent layer for {@see InheritedHooks}: its hooks are overridden by the child.
 */
abstract class InheritedHooksBase
{
    /** @var list<string> */
    protected array $log = [];

    #[BeforeTest(priority: 1000)]
    public function setUp(): void
    {
        $this->log[] = 'base-setUp';
    }

    #[BeforeTest(priority: 1000)]
    public function prepare(): void
    {
        $this->log[] = 'base-prepare';
    }

    #[BeforeTest(priority: -1000)]
    public function record(): void
    {
        $this->log[] = 'base-record';
    }
}
