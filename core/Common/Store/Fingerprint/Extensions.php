<?php

declare(strict_types=1);

namespace Testo\Common\Store\Fingerprint;

use Testo\Common\Store\FingerprintContributor;

/**
 * Invalidates a store when the presence or version of the named PHP extensions changes. TIA uses it
 * for coverage drivers (`pcov`, `xdebug`): swapping the driver changes the observed coverage, so the
 * recorded impact data no longer describes what this environment would produce.
 *
 * @api
 */
final readonly class Extensions implements FingerprintContributor
{
    /** @var non-empty-list<non-empty-string> */
    private array $names;

    /**
     * @param non-empty-string ...$names Extension names, order-insensitive.
     */
    public function __construct(string ...$names)
    {
        $names === [] and throw new \InvalidArgumentException('At least one extension name is required.');
        \sort($names);
        $this->names = $names;
    }

    #[\Override]
    public function key(): string
    {
        return 'ext';
    }

    #[\Override]
    public function value(): string
    {
        $parts = \array_map(
            static function (string $name): string {
                $version = \phpversion($name);
                return $name . ':' . ($version === false ? '-' : $version);
            },
            $this->names,
        );

        return \implode(';', $parts);
    }
}
