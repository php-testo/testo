<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Output\Rendering\Color;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Test;

#[Test]
#[Covers(Style::class)]
final class StyleTest
{
    public function colorStateRoundTrips(): void
    {
        $saved = Style::areColorsEnabled();

        try {
            Style::setColorsEnabled(false);
            Assert::false(Style::areColorsEnabled());

            Style::setColorsEnabled(true);
            Assert::true(Style::areColorsEnabled());
        } finally {
            Style::setColorsEnabled($saved);
        }
    }

    public function colorizeWrapsInColorAndResetWhenEnabled(): void
    {
        Assert::same(
            self::withColors(true, static fn(): string => Style::colorize('hi', Color::Green)),
            Color::Green->value . 'hi' . Color::Reset->value,
        );
    }

    public function colorizeReturnsRawTextWhenDisabled(): void
    {
        Assert::same(
            self::withColors(false, static fn(): string => Style::colorize('hi', Color::Green)),
            'hi',
        );
    }

    public function boldWrapsWhenEnabledAndPassesThroughWhenDisabled(): void
    {
        Assert::same(
            self::withColors(true, static fn(): string => Style::bold('x')),
            Color::Bold->value . 'x' . Color::Reset->value,
        );
        Assert::same(self::withColors(false, static fn(): string => Style::bold('x')), 'x');
    }

    public function dimWrapsWhenEnabledAndPassesThroughWhenDisabled(): void
    {
        Assert::same(
            self::withColors(true, static fn(): string => Style::dim('x')),
            Color::Dim->value . 'x' . Color::Reset->value,
        );
        Assert::same(self::withColors(false, static fn(): string => Style::dim('x')), 'x');
    }

    public function bannerPadsAndWrapsWhenEnabled(): void
    {
        Assert::same(
            self::withColors(true, static fn(): string => Style::banner('OK', Color::Green, Color::White)),
            Color::White->value . Color::Green->value . Color::Bold->value . ' OK ' . Color::Reset->value,
        );
    }

    public function bannerJustPadsWhenDisabled(): void
    {
        Assert::same(
            self::withColors(false, static fn(): string => Style::banner('OK', Color::Green)),
            ' OK ',
        );
    }

    public function semanticHelpersColorizeWithTheirColor(): void
    {
        Assert::same(
            self::withColors(true, static fn(): string => Style::success('a')),
            Color::Green->value . 'a' . Color::Reset->value,
        );
        Assert::same(
            self::withColors(true, static fn(): string => Style::error('a')),
            Color::Red->value . 'a' . Color::Reset->value,
        );
        Assert::same(
            self::withColors(true, static fn(): string => Style::warning('a')),
            Color::Yellow->value . 'a' . Color::Reset->value,
        );
        Assert::same(
            self::withColors(true, static fn(): string => Style::info('a')),
            Color::Cyan->value . 'a' . Color::Reset->value,
        );
    }

    /**
     * Runs $fn with colors forced to $enabled, restoring the global flag afterwards so the test
     * leaves no global state behind.
     */
    private static function withColors(bool $enabled, \Closure $fn): string
    {
        $saved = Style::areColorsEnabled();
        Style::setColorsEnabled($enabled);

        try {
            return $fn();
        } finally {
            Style::setColorsEnabled($saved);
        }
    }
}
