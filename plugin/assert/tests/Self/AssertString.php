<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertString as AssertStringImpl;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::string()
 */
#[Test]
#[Covers(Assert::class, 'string')]
#[Covers(AssertStringImpl::class)]
final class AssertString
{
    public function checkStringDataType(): void
    {
        // This assertion checks incoming data type
        Assert::string("This is string");
        Assert::string("");
    }

    public function checkWrongDataType(): never
    {
        Expect::exception(AssertionException::class);
        Assert::string([666]);
    }

    public function contains(): never
    {
        Assert::string("What makes PHP the best programming language?")->contains("PHP the best");
        Assert::string("string")->contains("str");
        Assert::string("string")->contains(""); // works for empty strings too

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("What makes PHP the best programming language?")->contains("PHP is dying", 'my wonderful message');
    }

    public function notContains(): never
    {
        Assert::string("abcde")->contains("abc")->notContains('zxcv');
        Assert::string("string")->contains("str")->notContains('brr');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("string")->notContains('str', 'my wonderful message');
    }

    public function startsWith(): never
    {
        Assert::string("Name must contain at least 5 characters.")->startsWith('Name must')->contains('least');
        Assert::string("string")->startsWith("");

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("string")->startsWith('ring', 'my wonderful message');
    }

    public function endsWith(): never
    {
        Assert::string("report.json")->endsWith('.json')->startsWith('report');
        Assert::string("string")->endsWith("");

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("string")->endsWith('str', 'my wonderful message');
    }

    public function matchesPattern(): never
    {
        Assert::string("2026-09-26")->matchesPattern('/^\d{4}-\d{2}-\d{2}$/');
        Assert::string("string")->matchesPattern('/str/');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("26.09.2026")->matchesPattern('/^\d{4}-\d{2}-\d{2}$/', 'my wonderful message');
    }

    public function notMatchesPattern(): never
    {
        Assert::string("no trailing space")->notMatchesPattern('/\s$/');
        Assert::string("string")->notMatchesPattern('/^ring/');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("trailing space ")->notMatchesPattern('/\s$/', 'my wonderful message');
    }

    public function matchesPatternWithFlags(): void
    {
        Assert::string("Hello World")->matchesPattern('/^hello world$/i')->notMatchesPattern('/^hello world$/');
        Assert::string("Привет")->matchesPattern('/^\w{6}$/u')->notMatchesPattern('/^\w{6}$/');
    }

    public function patternMatchersChainWithOtherMatchers(): void
    {
        Assert::string("report-2026.json")
            ->startsWith('report')
            ->matchesPattern('/-\d{4}\./')
            ->notMatchesPattern('/\s/')
            ->endsWith('.json');
    }

    public function matchesPatternRejectsInvalidPattern(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Invalid pattern /(/')
            ->withMessageContaining('missing closing parenthesis');
        Assert::string("string")->matchesPattern('/(/');
    }

    public function notMatchesPatternRejectsInvalidPattern(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Invalid pattern no-delimiters');
        Assert::string("string")->notMatchesPattern('no-delimiters');
    }

    public function invalidPatternEmitsNoWarning(): void
    {
        $warnings = [];
        \set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });

        try {
            Assert::string("string")->matchesPattern('/(/');
        } catch (\InvalidArgumentException) {
        } finally {
            \restore_error_handler();
        }

        Assert::same($warnings, []);
    }
}
