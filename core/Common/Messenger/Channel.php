<?php

declare(strict_types=1);

namespace Testo\Common\Messenger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Testo\Common\Messenger;
use Testo\Core\Log\Level;

/**
 * A channel-bound message handler — and a PSR-3 logger for that channel.
 *
 * Obtained via {@see Messenger::channel()}. Lets a producer (test code, middleware, or another
 * plugin) write into a single named channel of the hub without knowing about it:
 *
 * ```php
 * $sql = $messenger->channel('sql');
 * $sql->write('SELECT 1');                    // raw write
 * $sql->info('query {q} took {ms}ms', [...]); // PSR-3 logging into the same channel
 * ```
 *
 * As a {@see LoggerInterface}, every log call becomes a {@see \Testo\Core\Log\Message}: the PSR-3
 * level maps to a {@see Level}, the message is interpolated per the PSR-3 placeholder rules
 * (`{key}` ← `$context[key]`) for the displayable content, and the raw context is kept alongside.
 *
 * @api
 */
final readonly class Channel implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param non-empty-string $name
     * @internal Use {@see Messenger::channel()} to obtain an instance.
     */
    public function __construct(
        private Messenger $messenger,
        public string $name,
    ) {}

    /**
     * @param non-empty-string $content
     * @param array<string, mixed> $context Structured context attached to the message.
     */
    public function write(string $content, Level $level = Level::Info, array $context = []): void
    {
        $this->messenger->log($this->name, $content, $level, $context);
    }

    /**
     * @param Level|mixed $level A {@see Level} or a PSR-3 level string ({@see \Psr\Log\LogLevel});
     *        an unknown level falls back to {@see Level::Info}.
     * @param array<string, mixed> $context
     * @psalm-suppress MoreSpecificImplementedParamType, ArgumentTypeCoercion
     */
    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->write(
            self::interpolate((string) $message, $context),
            self::resolveLevel($level),
            $context,
        );
    }

    /**
     * @param Level|mixed $level
     */
    private static function resolveLevel(mixed $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }

        return Level::tryFrom((string) $level) ?? Level::Info;
    }

    /**
     * PSR-3 message interpolation: replace `{key}` tokens with stringable context values.
     *
     * @param array<string, mixed> $context
     */
    private static function interpolate(string $message, array $context): string
    {
        if ($context === [] || !\str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $value) {
            if (\is_scalar($value) || $value === null || $value instanceof \Stringable) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return $replacements === [] ? $message : \strtr($message, $replacements);
    }
}
