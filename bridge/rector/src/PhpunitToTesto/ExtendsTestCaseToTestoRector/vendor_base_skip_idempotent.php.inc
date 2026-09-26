<?php

#[\Testo\Skip('Extends Tests\Bridge\Rector\Stub\VendorTestCase, a PHPUnit base from vendor: rewrite the base for Testo, then remove this attribute')]
final class VendorRerunTest extends \Tests\Bridge\Rector\Stub\VendorTestCase
{
    #[\Testo\Test]
    public function testDirect(): void
    {
    }
}
