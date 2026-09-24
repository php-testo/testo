<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Set;

/**
 * Typed handles for the conversion sets shipped under `config/`.
 *
 * Reference these instead of hand-writing the set path in your `rector.php`:
 *
 * ```php
 * use Testo\Bridge\Rector\Set\TestoRectorSetList;
 *
 * return RectorConfig::configure()
 *     ->withPaths([__DIR__ . '/tests'])
 *     ->withSets([TestoRectorSetList::TESTO_TO_PHPUNIT]);
 * ```
 *
 * The constants are absolute paths to the set files, so they also work with
 * `$rectorConfig->import(...)`. Mirrors the {@see \Rector\PHPUnit\Set\PHPUnitSetList} convention.
 *
 * @api
 */
final class TestoRectorSetList
{
    /**
     * Testo -> PHPUnit. See {@see config/testo-to-phpunit.php}.
     *
     * @var string
     */
    public const TESTO_TO_PHPUNIT = __DIR__ . '/../../config/testo-to-phpunit.php';

    /**
     * PHPUnit -> Testo. See {@see config/phpunit-to-testo.php}.
     *
     * @var string
     */
    public const PHPUNIT_TO_TESTO = __DIR__ . '/../../config/phpunit-to-testo.php';

    /**
     * Pest -> Testo. See {@see config/pest-to-testo.php}.
     *
     * @var string
     */
    public const PEST_TO_TESTO = __DIR__ . '/../../config/pest-to-testo.php';

    /**
     * PHPUnit mocks -> Double. See {@see config/phpunit-to-double.php}.
     *
     * @var string
     */
    public const PHPUNIT_TO_DOUBLE = __DIR__ . '/../../config/phpunit-to-double.php';

    /**
     * PHPUnit mocks -> Mockery. See {@see config/phpunit-to-mockery.php}.
     *
     * @var string
     */
    public const PHPUNIT_TO_MOCKERY = __DIR__ . '/../../config/phpunit-to-mockery.php';

    /**
     * Mockery -> Double. See {@see config/mockery-to-double.php}.
     *
     * @var string
     */
    public const MOCKERY_TO_DOUBLE = __DIR__ . '/../../config/mockery-to-double.php';
}
