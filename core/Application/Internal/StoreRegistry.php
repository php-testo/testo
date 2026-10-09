<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Internal\Container\Container;
use Internal\Path;
use Testo\Application\Config\StoreConfig;
use Testo\Application\Internal\Store\FileStore;
use Testo\Application\Internal\Store\NullStore;
use Testo\Common\Messenger;
use Testo\Common\Store;
use Testo\Common\Store\StoreDefinition;
use Testo\Common\Store\Stores;
use Testo\Common\Store\StoreScope;
use Testo\Core\Context\SuiteContext;

/**
 * Default {@see Stores} implementation.
 *
 * A single application-scoped service. It holds the live container rather than a captured scope, so a
 * suite-scoped {@see open()} resolves the {@see SuiteContext} of whichever suite is active at call
 * time — the same lazy-resolution seam {@see SuiteProvider} uses. Opening does no I/O; the returned
 * {@see FileStore} touches disk only when read or written.
 *
 * @internal
 */
final readonly class StoreRegistry implements Stores
{
    public function __construct(
        private Container $container,
        private StoreConfig $config,
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function open(StoreDefinition $definition): Store
    {
        # Scope validation is a programming-error check — it must fire regardless of configuration,
        # or a plugin bug would surface only for users who happen to have stores enabled.
        $base = $this->baseDir();
        $file = match ($definition->scope) {
            StoreScope::Application => $base->join('app', $definition->name . '.json'),
            StoreScope::Suite => $this->suiteDir($base)->join($definition->name . '.json'),
        };

        return $this->config->enabled
            ? new FileStore($base, $file, $definition, $this->messenger)
            : new NullStore();
    }

    /**
     * @return non-empty-string
     */
    private static function slug(string $name): string
    {
        $slug = \trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($name)), '-');

        return \substr($slug, 0, 40) ?: 'suite';
    }

    private function baseDir(): Path
    {
        $env = \getenv('TESTO_STORE_DIR');
        $dir = $env === false || $env === '' ? $this->config->directory : $env;

        return Path::create($dir)->absolute();
    }

    private function suiteDir(Path $base): Path
    {
        $this->container->has(SuiteContext::class) or throw new \LogicException(
            'A suite-scoped store can only be opened within a suite scope.',
        );

        $name = $this->container->get(SuiteContext::class)->name;
        # A hash suffix disambiguates slug collisions and case-insensitive filesystems.
        $slug = self::slug($name) . '-' . \substr(\sha1($name), 0, 8);

        return $base->join('suite', $slug);
    }
}
