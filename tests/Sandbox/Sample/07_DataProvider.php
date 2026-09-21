<?php

declare(strict_types=1);

/**
 * Slide 7. Method-based data provider: the shape is the same in both frameworks.
 */

namespace Sample\PhpUnit {

    use App\Identity\EmailValidator;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    final class EmailValidatorTest extends TestCase
    {
        #[DataProvider('invalidEmails')]
        public function testRejectsInvalidEmail(string $email): void
        {
            $this->assertFalse((new EmailValidator())->isValid($email));
        }

        public static function invalidEmails(): iterable
        {
            yield 'no at sign' => ['john.example.com'];
            yield 'no domain' => ['john@'];
            yield 'spaces' => ['john doe@example.com'];
            yield 'empty' => [''];
        }
    }
}

namespace Sample\Testo {

    use App\Identity\EmailValidator;
    use Testo\Assert;
    use Testo\Data\DataProvider;
    use Testo\Test;

    #[Test]
    final class EmailValidatorTest
    {
        #[DataProvider('invalidEmails')]
        public function rejectsInvalidEmail(string $email): void
        {
            Assert::false((new EmailValidator())->isValid($email));
        }

        public static function invalidEmails(): iterable
        {
            yield 'no at sign' => ['john.example.com'];
            yield 'no domain' => ['john@'];
            yield 'spaces' => ['john doe@example.com'];
            yield 'empty' => [''];
        }
    }
}
