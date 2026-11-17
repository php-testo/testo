<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Output\Terminal\ProgramStatus;
use Testo\Test;

/**
 * The OSC 7501 wire format as the terminal reads it: `ESC ] 7501 ; key=value:… ESC \`, with the message
 * base64-encoded.
 */
#[Test]
#[Covers(ProgramStatus::class)]
final class ProgramStatusTest
{
    public function workingCarriesProgressAndEncodedMessage(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->working(40, 'Unit'));

        Assert::same($output, "\033]7501;state=working:app=testo:progress=40:msg=VW5pdA==\033\\");
    }

    public function indeterminateWorkingOmitsProgressAndEmptyMessage(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->working());

        Assert::same($output, "\033]7501;state=working:app=testo\033\\");
    }

    public function blockedIsAQuestion(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->blocked('Overwrite?'));

        Assert::string($output)->contains('state=blocked:app=testo:kind=question:msg=');
    }

    public function finalStatesCarryNoProgress(): void
    {
        $output = self::capture(static function (ProgramStatus $status): void {
            $status->done('ok');
            $status->error('bad');
            $status->clear();
        });

        Assert::same(\substr_count($output, 'progress='), 0);
        Assert::string($output)->contains('state=done:app=testo:msg=b2s=');
        Assert::string($output)->contains('state=error:app=testo:msg=YmFk');
        Assert::string($output)->contains('state=clear:app=testo');
    }

    public function progressIsClampedToPercent(): void
    {
        $output = self::capture(static function (ProgramStatus $status): void {
            $status->working(150);
            $status->working(-5);
        });

        Assert::string($output)->contains('progress=100');
        Assert::string($output)->contains('progress=0');
    }

    public function messageIsFoldedIntoOneLine(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->done("two\nlines\t"));

        // The terminal discards a report whose message decodes to a control character.
        Assert::string($output)->contains('msg=' . \base64_encode('two lines'));
    }

    public function c1ControlsAndBrokenUtf8AreNotSent(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->done("a\u{0085}b\xFFc"));

        \preg_match('/msg=([^\033]+)/', $output, $match);
        $decoded = (string) \base64_decode($match[1], true);
        Assert::true(\mb_check_encoding($decoded, 'UTF-8'));
        Assert::same(\preg_match('/\p{Cc}/u', $decoded), 0);
        Assert::string($decoded)->startsWith('a b');
    }

    public function oversizedMessageIsCutOnACharacterBoundary(): void
    {
        $output = self::capture(static fn(ProgramStatus $status) => $status->done(\str_repeat('я', 1500)));

        \preg_match('/msg=([^\033]+)/', $output, $match);
        $decoded = \base64_decode($match[1], true);
        Assert::same(\strlen((string) $decoded), 2048);
        Assert::true(\mb_check_encoding((string) $decoded, 'UTF-8'));
    }

    public function aRepeatedReportIsNotWrittenAgain(): void
    {
        $output = self::capture(static function (ProgramStatus $status): void {
            $status->working(10, 'Unit');
            $status->working(10, 'Unit');
            $status->working(11, 'Unit');
        });

        Assert::same(\substr_count($output, "\033]7501;"), 2);
    }

    public function aDisabledReporterTakesReportsSilently(): void
    {
        $status = ProgramStatus::disabled();

        $status->working(10);
        $status->done();

        Assert::false($status->isEnabled());
    }

    public function aStreamThatIsNotATerminalIsNotReportedTo(): void
    {
        $stream = \fopen('php://memory', 'w+');
        \assert($stream !== false);

        Assert::false(ProgramStatus::detect($stream)->isEnabled());

        \fclose($stream);
    }

    /**
     * @param \Closure(ProgramStatus): void $scenario
     */
    private static function capture(\Closure $scenario): string
    {
        $stream = \fopen('php://memory', 'w+');
        \assert($stream !== false);

        try {
            $scenario(new ProgramStatus($stream));

            \rewind($stream);
            return (string) \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }
    }
}
