<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Test;

final class DataUnion
{
    public static function adminsProvider(): array
    {
        return [
            'root' => ['root', true],
            'admin' => ['admin', true],
        ];
    }

    public static function usersProvider(): iterable
    {
        yield 'alice' => ['alice', false];
        yield 'bob' => ['bob', false];
    }

    #[Test]
    #[\Testo\Data\DataCross(
        new \Testo\Data\DataUnion(
            new DataProvider('adminsProvider'),
            new DataProvider('usersProvider'),
        ),
        new DataSet(['read'], 'read'),
    )]
    public function crossWithUnion(string $user, bool $isAdmin, string $permission): void
    {
        // (2 admins + 2 users) × 1 permission = 4 combinations
        Assert::same($permission, 'read');
        Assert::true(\in_array([$user, $isAdmin], [
            ['root', true],
            ['admin', true],
            ['alice', false],
            ['bob', false],
        ], true));
    }

    #[Test]
    #[\Testo\Data\DataZip(
        new \Testo\Data\DataUnion(
            new DataProvider('adminsProvider'),
            new DataProvider('usersProvider'),
        ),
        new DataProvider('permissionsProvider'),
    )]
    public function zipWithUnion(string $user, bool $isAdmin, string $permission): void
    {
        // zip stops at shortest: 4 users | 3 permissions = 3 iterations
        Assert::true(\in_array([$user, $isAdmin, $permission], [
            ['root', true, 'read'],
            ['admin', true, 'write'],
            ['alice', false, 'delete'],
        ], true));
    }

    public static function permissionsProvider(): array
    {
        return [
            'read' => ['read'],
            'write' => ['write'],
            'delete' => ['delete'],
        ];
    }

    #[Test]
    #[\Testo\Data\DataUnion(
        new DataProvider('adminsProvider'),
        new DataProvider('usersProvider'),
    )]
    public function simpleUnion(string $user, bool $isAdmin): void
    {
        // 2 admins + 2 users = 4 iterations
        Assert::true(\in_array([$user, $isAdmin], [
            ['root', true],
            ['admin', true],
            ['alice', false],
            ['bob', false],
        ], true));
    }
}
