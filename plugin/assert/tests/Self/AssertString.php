<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::string()
 */
final class AssertString
{
    #[Test]
    public function checkStringDataType(): void
    {
        // This assertion checks incoming data type
        Assert::string("This is string");
        Assert::string("");
    }

    #[Test]
    public function checkStringContains(): void
    {
        // This assertion checks that haystack-string contains needle-part.
        Assert::string("What makes PHP the best programming language?")->contains("PHP the best");
        Assert::string("string")->contains("str");
        Assert::string("string")->contains(""); // works for empty strings too
    }

    #[Test]
    public function chainLogShowcase(): void
    {
        Assert::string("abcde")->contains("abc")->notContains('zxcv');
        Assert::string("string")->contains("str")->notContains('brr');
    }

    #[Test]
    public function checkWrongDataType(): void
    {
        Expect::exception(AssertionException::class);
        Assert::string([666]);
    }

    #[Test]
    public function checkStringDoesNotContain(): void
    {
        Expect::exception(AssertionException::class);
        Assert::string("What makes PHP the best programming language?")->contains("PHP is dying");
    }
}
