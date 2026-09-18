<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Store;

use Testo\Application\Internal\Store\NullStore;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NullStore::class)]
final class NullStoreTest
{
    public function loadIsAlwaysNull(): void
    {
        Assert::null((new NullStore())->load());
    }

    public function saveAndDeleteAreSilentNoOps(): void
    {
        $store = new NullStore();

        $store->save(['v' => 1]);
        $store->delete();

        Assert::null($store->load());
    }

    public function updateNeverInvokesTheClosure(): void
    {
        $called = false;

        (new NullStore())->update(static function () use (&$called): array {
            $called = true;
            return [];
        });

        Assert::false($called);
    }
}
