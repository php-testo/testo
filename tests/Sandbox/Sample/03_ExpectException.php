<?php

declare(strict_types=1);

/**
 * Slide 3. Expecting an exception with a cause: one `Expect` chain vs `expectException*()` plus try/catch
 * for the `previous` exception (PHPUnit has no built-in way to check it).
 */

namespace Sample\PhpUnit {

    use App\Billing\PaymentFailed;
    use App\Billing\PaymentGateway;
    use App\Billing\Wallet;
    use PHPUnit\Framework\TestCase;

    final class WalletTest extends TestCase
    {
        public function testRejectsChargeWhenGatewayDeclines(): void
        {
            $wallet = new Wallet(new PaymentGateway(declineAll: true));

            $this->expectException(PaymentFailed::class);
            $this->expectExceptionMessage('Charge of 1500 declined');

            $wallet->charge(1500);
        }

        public function testKeepsGatewayErrorAsCause(): void
        {
            $wallet = new Wallet(new PaymentGateway(declineAll: true));

            try {
                $wallet->charge(1500);
                $this->fail('PaymentFailed was not thrown');
            } catch (PaymentFailed $e) {
                $this->assertSame(PaymentFailed::DECLINED, $e->getCode());

                $previous = $e->getPrevious();
                $this->assertInstanceOf(PaymentGateway\Declined::class, $previous);
                $this->assertSame('insufficient_funds', $previous->getMessage());
            }
        }
    }
}

namespace Sample\Testo {

    use App\Billing\PaymentFailed;
    use App\Billing\PaymentGateway;
    use App\Billing\Wallet;
    use Testo\Expect;
    use Testo\Test;

    /**
     * Testo's `Expect` allows a single chain to assert the exception type, message, code, and cause, without try/catch.
     */
    #[Test]
    final class WalletTest
    {
        public function rejectsChargeWhenGatewayDeclines(): never
        {
            $wallet = new Wallet(new PaymentGateway(declineAll: true));

            Expect::exception(PaymentFailed::class)
                ->withMessage('Charge of 1500 declined')
                ->withCode(PaymentFailed::DECLINED)
                ->withPrevious(
                    PaymentGateway\Declined::class,
                    static fn($cause) => $cause->withMessage('insufficient_funds'),
                );

            $wallet->charge(1500);
        }
    }
}
