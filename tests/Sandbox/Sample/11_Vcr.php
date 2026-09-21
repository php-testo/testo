<?php

declare(strict_types=1);

/**
 * Slide 11. Recording HTTP with PHP-VCR.
 * PHPUnit: the `phpunit-testlistener-vcr` package (`@vcr` annotation) is unmaintained and does not support
 * PHPUnit 10+, so the cassette is inserted and ejected by hand in `setUp()`/`tearDown()`.
 * Testo: `#[VCR('cassette')]` on the test (or the class), self-wiring, no plugin registration.
 */

namespace Sample\PhpUnit {

    use App\Rates\ExchangeRatesClient;
    use PHPUnit\Framework\TestCase;
    use VCR\VCR;

    final class ExchangeRatesClientTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            VCR::configure()
                ->setCassettePath(__DIR__ . '/cassettes')
                ->setMode(VCR::MODE_NONE);
            VCR::turnOn();
            VCR::insertCassette('exchange-rates');
        }

        protected function tearDown(): void
        {
            VCR::eject();
            VCR::turnOff();
            parent::tearDown();
        }

        public function testFetchesRateForCurrencyPair(): void
        {
            $rate = (new ExchangeRatesClient())->rate(from: 'EUR', to: 'USD');

            $this->assertGreaterThan(1.0, $rate);
        }
    }
}

namespace Sample\Testo {

    use App\Rates\ExchangeRatesClient;
    use Testo\Assert;
    use Testo\Bridge\VCR;
    use Testo\Bridge\VCR\RecordMode;
    use Testo\Test;

    #[Test]
    final class ExchangeRatesClientTest
    {
        #[VCR('exchange-rates', mode: RecordMode::None)]
        public function fetchesRateForCurrencyPair(): void
        {
            $rate = (new ExchangeRatesClient())->rate(from: 'EUR', to: 'USD');

            Assert::float($rate)->greaterThan(1.0);
        }
    }
}
