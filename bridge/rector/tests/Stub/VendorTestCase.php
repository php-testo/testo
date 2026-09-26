<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Stub;

use PHPUnit\Framework\TestCase;

/**
 * A PHPUnit base class outside the paths a fixture run processes, like a framework's test case
 * installed in vendor.
 */
abstract class VendorTestCase extends TestCase {}
