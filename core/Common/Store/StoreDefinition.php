<?php

declare(strict_types=1);

namespace Testo\Common\Store;

/**
 * Declaration of a store: what it is called, which payload version it carries, where it lives, and
 * what invalidates it. Immutable — build it once in a static factory on the owning plugin (PHP does
 * not allow `new` in class constants).
 *
 * @api
 */
final readonly class StoreDefinition
{
    /**
     * @param non-empty-string $name Dotted, lowercase name namespaced by owner: `impact.index`,
     *        `retry.flaky-history`, `symfony-console.cache`. Doubles as the on-disk file name.
     * @param int $schema Payload schema version, >= 1. A recorded payload with a different schema
     *        counts as absent — there are no migrations, the owner rebuilds from scratch.
     * @param StoreScope $scope Whether the document is shared per run or kept per suite.
     * @param list<FingerprintContributor> $fingerprint Environment inputs that invalidate the payload
     *        when they change. Keys must be unique within the list. The owner adds its own (e.g. a
     *        hash of its config) when it wants the store dropped on a configuration change — the
     *        suite key itself is only the suite name.
     */
    public function __construct(
        public string $name,
        public int $schema,
        public StoreScope $scope = StoreScope::Suite,
        public array $fingerprint = [],
    ) {
        \preg_match('/^[a-z0-9]+(-[a-z0-9]+)*(\.[a-z0-9]+(-[a-z0-9]+)*)*$/', $name) === 1
            or throw new \InvalidArgumentException(
                \sprintf('Store name must be dotted lowercase (e.g. "impact.index"), "%s" given.', $name),
            );
        $schema >= 1 or throw new \InvalidArgumentException(
            \sprintf('Store schema version must be >= 1, %d given.', $schema),
        );

        $keys = [];
        foreach ($fingerprint as $contributor) {
            $key = $contributor->key();
            isset($keys[$key]) and throw new \InvalidArgumentException(
                \sprintf('Duplicate fingerprint key "%s" in store "%s".', $key, $name),
            );
            $keys[$key] = true;
        }
    }
}
