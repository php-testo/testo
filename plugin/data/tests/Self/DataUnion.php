<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Data\DataUnion as DataUnionImpl;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Test;

/**
 * @see DataUnionImpl
 */
#[Test]
#[Covers(DataUnionImpl::class)]
#[Covers(DataProviderInterceptor::class)]
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

    public static function permissionsProvider(): array
    {
        return [
            'read' => ['read'],
            'write' => ['write'],
            'delete' => ['delete'],
        ];
    }

    /**
     * A DataUnion used as one axis of a DataCross concatenates its providers before crossing:
     * (2 admins + 2 users) x 1 permission = 4 combinations.
     */
    #[\Testo\Data\DataCross(
        new DataUnionImpl(
            new DataProvider('adminsProvider'),
            new DataProvider('usersProvider'),
        ),
        new DataSet(['read'], 'read'),
    )]
    public function crossWithUnion(string $user, bool $isAdmin, string $permission): void
    {
        Assert::same($permission, 'read');
        Assert::true(\in_array([$user, $isAdmin], [
            ['root', true],
            ['admin', true],
            ['alice', false],
            ['bob', false],
        ], true));
    }

    /**
     * A DataUnion axis zipped against another provider stops at the shortest axis:
     * 4 unioned users | 3 permissions = 3 iterations.
     */
    #[\Testo\Data\DataZip(
        new DataUnionImpl(
            new DataProvider('adminsProvider'),
            new DataProvider('usersProvider'),
        ),
        new DataProvider('permissionsProvider'),
    )]
    public function zipWithUnion(string $user, bool $isAdmin, string $permission): void
    {
        Assert::true(\in_array([$user, $isAdmin, $permission], [
            ['root', true, 'read'],
            ['admin', true, 'write'],
            ['alice', false, 'delete'],
        ], true));
    }

    /**
     * DataUnion as a standalone attribute concatenates its providers into one sequence:
     * 2 admins + 2 users = 4 iterations.
     */
    #[\Testo\Data\DataUnion(
        new DataProvider('adminsProvider'),
        new DataProvider('usersProvider'),
    )]
    public function simpleUnion(string $user, bool $isAdmin): void
    {
        Assert::true(\in_array([$user, $isAdmin], [
            ['root', true],
            ['admin', true],
            ['alice', false],
            ['bob', false],
        ], true));
    }
}
