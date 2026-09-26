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

    public function matchesRegex(): never
    {
        Assert::string("2026-09-26")->matchesRegex('/^\d{4}-\d{2}-\d{2}$/');
        Assert::string("string")->matchesRegex('/str/');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("26.09.2026")->matchesRegex('/^\d{4}-\d{2}-\d{2}$/', 'my wonderful message');
    }

    public function notMatchesRegex(): never
    {
        Assert::string("no trailing space")->notMatchesRegex('/\s$/');
        Assert::string("string")->notMatchesRegex('/^ring/');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::string("trailing space ")->notMatchesRegex('/\s$/', 'my wonderful message');
    }

    public function matchesRegexWithFlags(): void
    {
        Assert::string("Hello World")->matchesRegex('/^hello world$/i')->notMatchesRegex('/^hello world$/');
        Assert::string("Привет")->matchesRegex('/^\w{6}$/u')->notMatchesRegex('/^\w{6}$/');
    }

    public function regexMatchersChainWithOtherMatchers(): void
    {
        Assert::string("report-2026.json")
            ->startsWith('report')
            ->matchesRegex('/-\d{4}\./')
            ->notMatchesRegex('/\s/')
            ->endsWith('.json');
    }

    public function matchesRegexRejectsInvalidPattern(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Invalid pattern /(/')
            ->withMessageContaining('missing closing parenthesis');
        Assert::string("string")->matchesRegex('/(/');
    }

    public function notMatchesRegexRejectsInvalidPattern(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Invalid pattern no-delimiters');
        Assert::string("string")->notMatchesRegex('no-delimiters');
    }

    public function invalidPatternEmitsNoWarning(): void
    {
        $warnings = [];
        \set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });

        try {
            Assert::string("string")->matchesRegex('/(/');
        } catch (\InvalidArgumentException) {
        } finally {
            \restore_error_handler();
        }

        Assert::same($warnings, []);
    }
}
