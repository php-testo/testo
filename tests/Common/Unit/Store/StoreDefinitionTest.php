<?php

declare(strict_types=1);

namespace Tests\Common\Unit\Store;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Store\FingerprintContributor;
use Testo\Common\Store\StoreDefinition;
use Testo\Common\Store\StoreScope;
use Testo\Expect;
use Testo\Test;

#[Covers(StoreDefinition::class)]
final class StoreDefinitionTest
{
    #[Test]
    public function keepsTheGivenValues(): void
    {
        $definition = new StoreDefinition('impact.index', 2, StoreScope::Application);

        Assert::same($definition->name, 'impact.index');
        Assert::same($definition->schema, 2);
        Assert::same($definition->scope, StoreScope::Application);
    }

    #[Test]
    public function defaultsToSuiteScopeWithoutFingerprint(): void
    {
        $definition = new StoreDefinition('timing.durations', 1);

        Assert::same($definition->scope, StoreScope::Suite);
        Assert::same($definition->fingerprint, []);
    }

    #[Test]
    public function acceptsDashesInEverySegment(): void
    {
        $definition = new StoreDefinition('symfony-console.command-cache', 1);

        Assert::same($definition->name, 'symfony-console.command-cache');
    }

    #[Test]
    public function rejectsANameThatIsNotDottedLowercase(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        new StoreDefinition('Impact_Index', 1);
    }

    #[Test]
    public function rejectsADanglingDash(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        new StoreDefinition('retry.flaky-', 1);
    }

    #[Test]
    public function rejectsDuplicateFingerprintKeys(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        new StoreDefinition('impact.index', 1, fingerprint: [
            self::contributor('php'),
            self::contributor('php'),
        ]);
    }

    private static function contributor(string $key): FingerprintContributor
    {
        return new class ($key) implements FingerprintContributor {
            public function __construct(
                private readonly string $key,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function value(): string
            {
                return 'v';
            }
        };
    }

    #[Test]
    public function rejectsAnEmptyName(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        new StoreDefinition('', 1);
    }

    #[Test]
    public function rejectsASchemaBelowOne(): never
    {
        Expect::exception(\InvalidArgumentException::class);

        new StoreDefinition('impact.index', 0);
    }
}
