<?php

declare(strict_types=1);

/**
 * Slide 8. Two independent axes (currency x locale) shared by several tests.
 * PHPUnit has no cartesian product of providers out of the box; the usual answer is a third-party package:
 * `composer require --dev rawr/phpunit-data-provider` (github.com/t-regx/phpunit-data-provider),
 * plus a dedicated method per combination. Testo composes the axes at the test with `#[DataCross]`.
 */

namespace Sample\PhpUnit {

    use App\Money\MoneyFormatter;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;
    use TRegx\PhpUnit\DataProviders\DataProvider as Providers;

    final class MoneyFormatterTest extends TestCase
    {
        #[DataProvider('currencyLocaleMatrix')]
        public function testFormatsEveryCurrencyInEveryLocale(string $currency, string $locale): void
        {
            $formatted = (new MoneyFormatter($locale))->format(123_456, $currency);

            $this->assertStringContainsString('1', $formatted);
        }

        #[DataProvider('currencyLocaleMatrix')]
        public function testParsesBackWhatItFormatted(string $currency, string $locale): void
        {
            $formatter = new MoneyFormatter($locale);

            $this->assertSame(123_456, $formatter->parse($formatter->format(123_456, $currency), $currency));
        }

        #[DataProvider('currencies')]
        public function testRoundsToMinorUnits(string $currency): void
        {
            $this->assertSame(100, (new MoneyFormatter('en_US'))->round(100.004, $currency));
        }

        public static function currencies(): Providers
        {
            return Providers::list('USD', 'EUR', 'JPY');
        }

        public static function locales(): Providers
        {
            return Providers::list('en_US', 'de_DE', 'ru_RU');
        }

        public static function currencyLocaleMatrix(): Providers
        {
            return Providers::cross(self::currencies(), self::locales());
        }
    }
}

namespace Sample\Testo {

    use App\Money\MoneyFormatter;
    use Testo\Assert;
    use Testo\Data\DataCross;
    use Testo\Data\DataProvider;
    use Testo\Test;

    #[Test]
    final class MoneyFormatterTest
    {
        #[DataCross(new DataProvider('currencies'), new DataProvider('locales'))]
        public function formatsEveryCurrencyInEveryLocale(string $currency, string $locale): void
        {
            $formatted = (new MoneyFormatter($locale))->format(123_456, $currency);

            Assert::string($formatted)->contains('1');
        }

        #[DataCross(new DataProvider('currencies'), new DataProvider('locales'))]
        public function parsesBackWhatItFormatted(string $currency, string $locale): void
        {
            $formatter = new MoneyFormatter($locale);

            Assert::same($formatter->parse($formatter->format(123_456, $currency), $currency), 123_456);
        }

        #[DataProvider('currencies')]
        public function roundsToMinorUnits(string $currency): void
        {
            Assert::same((new MoneyFormatter('en_US'))->round(100.004, $currency), 100);
        }

        public static function currencies(): iterable
        {
            return [['USD'], ['EUR'], ['JPY']];
        }

        public static function locales(): iterable
        {
            return [['en_US'], ['de_DE'], ['ru_RU']];
        }
    }
}
