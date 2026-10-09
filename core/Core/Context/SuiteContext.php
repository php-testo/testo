<?php

declare(strict_types=1);

namespace Testo\Core\Context;

/**
 * Identity of the suite currently being processed, bound into the per-suite container scope.
 *
 * Available from discovery onward — before {@see SuiteInfo} exists — so services that must key data
 * by suite (persistent stores, impact selection) can resolve which suite they are running in without
 * threading it through every call.
 *
 * @api
 */
final readonly class SuiteContext
{
    /**
     * @param non-empty-string $name Unique suite name, from {@see \Testo\Application\Config\SuiteConfig::$name}.
     */
    public function __construct(
        public string $name,
    ) {}
}
