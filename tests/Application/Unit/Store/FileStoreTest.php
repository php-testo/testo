<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Store;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\MessengerHub;
use Testo\Application\Internal\Store\FileStore;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Common\Store\FingerprintContributor;
use Testo\Common\Store\StoreDefinition;
use Testo\Test;

#[Covers(FileStore::class)]
final class FileStoreTest
{
    #[Test]
    public function savedPayloadReadsBackUnchanged(): void
    {
        $this->inTempDir(function (string $dir): void {
            $store = $this->store($dir);

            $store->save(['count' => 3, 'tests' => ['a', 'b']]);

            Assert::same($store->load(), ['count' => 3, 'tests' => ['a', 'b']]);
        });
    }

    #[Test]
    public function loadIsNullWhenNothingWasSaved(): void
    {
        $this->inTempDir(function (string $dir): void {
            Assert::null($this->store($dir)->load());
        });
    }

    #[Test]
    public function saveOverwritesThePreviousPayload(): void
    {
        $this->inTempDir(function (string $dir): void {
            $store = $this->store($dir);

            $store->save(['v' => 1]);
            $store->save(['v' => 2]);

            Assert::same($store->load(), ['v' => 2]);
        });
    }

    #[Test]
    public function aDifferentSchemaReadsAsAbsent(): void
    {
        $this->inTempDir(function (string $dir): void {
            $messenger = $this->messenger();
            $this->store($dir, schema: 1)->save(['v' => 1]);

            $store = $this->store($dir, schema: 2, messenger: $messenger);

            Assert::null($store->load());
            Assert::true($this->warned($messenger, 'schema mismatch'));
        });
    }

    #[Test]
    public function fingerprintDriftReadsAsAbsent(): void
    {
        $this->inTempDir(function (string $dir): void {
            $fingerprint = $this->mutableContributor();
            $messenger = $this->messenger();
            $store = $this->store($dir, fingerprint: [$fingerprint], messenger: $messenger);
            $store->save(['v' => 1]);

            $fingerprint->current = 'changed';

            Assert::null($store->load());
            Assert::true($this->warned($messenger, 'fingerprint drift'));
        });
    }

    #[Test]
    public function matchingFingerprintStillReads(): void
    {
        $this->inTempDir(function (string $dir): void {
            $store = $this->store($dir, fingerprint: [$this->mutableContributor()]);

            $store->save(['v' => 1]);

            Assert::same($store->load(), ['v' => 1]);
        });
    }

    #[Test]
    public function corruptedFileReadsAsAbsent(): void
    {
        $this->inTempDir(function (string $dir): void {
            $messenger = $this->messenger();
            $store = $this->store($dir, messenger: $messenger);
            $store->save(['v' => 1]);
            \file_put_contents($this->file($dir), '{not valid json');

            Assert::null($store->load());
            Assert::true($this->warned($messenger, 'corrupted'));
        });
    }

    #[Test]
    public function updateReceivesCurrentPayloadAndPersistsTheResult(): void
    {
        $this->inTempDir(function (string $dir): void {
            $store = $this->store($dir);
            $store->save(['hits' => 1]);

            $store->update(static fn(?array $current): array => ['hits' => $current['hits'] + 1]);

            Assert::same($store->load(), ['hits' => 2]);
        });
    }

    #[Test]
    public function updateStartsFromNullWhenAbsent(): void
    {
        $this->inTempDir(function (string $dir): void {
            $seen = 'unset';
            $store = $this->store($dir);

            $store->update(static function (?array $current) use (&$seen): array {
                $seen = $current;
                return ['created' => true];
            });

            Assert::null($seen);
            Assert::same($store->load(), ['created' => true]);
        });
    }

    #[Test]
    public function deleteRemovesTheFileAndIsIdempotent(): void
    {
        $this->inTempDir(function (string $dir): void {
            $store = $this->store($dir);
            $store->save(['v' => 1]);

            $store->delete();
            $store->delete();

            Assert::null($store->load());
            Assert::false(\is_file($this->file($dir)));
        });
    }

    #[Test]
    public function saveLeavesNoTemporaryFilesBehind(): void
    {
        $this->inTempDir(function (string $dir): void {
            $this->store($dir)->save(['v' => 1]);

            $leftovers = \glob(Path::create($dir)->join('app')->__toString() . '/*.tmp');
            Assert::same($leftovers, []);
        });
    }

    #[Test]
    public function firstWriteMarksTheBaseDirectorySelfIgnoring(): void
    {
        $this->inTempDir(function (string $dir): void {
            $this->store($dir)->save(['v' => 1]);

            $gitignore = $dir . '/.gitignore';
            Assert::true(\is_file($gitignore));
            Assert::same(\trim((string) \file_get_contents($gitignore)), '*');
        });
    }

    #[Test]
    public function anUnwritableLocationFailsOpenWithoutThrowing(): void
    {
        $this->inTempDir(function (string $dir): void {
            # A regular file where the base directory should be: every mkdir underneath must fail.
            $blocked = $dir . '/blocked';
            \file_put_contents($blocked, 'x');
            $messenger = $this->messenger();
            $store = $this->store($blocked, messenger: $messenger);

            $store->save(['v' => 1]);

            Assert::null($store->load());
            Assert::true($this->warned($messenger, 'directory unavailable'));
        });
    }

    private function store(
        string $base,
        int $schema = 1,
        array $fingerprint = [],
        ?Messenger $messenger = null,
    ): FileStore {
        $definition = new StoreDefinition('impact.index', $schema, fingerprint: $fingerprint);

        return new FileStore(
            Path::create($base),
            Path::create($this->file($base)),
            $definition,
            $messenger ?? $this->messenger(),
        );
    }

    private function file(string $base): string
    {
        return Path::create($base)->join('app', 'impact.index.json')->__toString();
    }

    private function mutableContributor(): FingerprintContributor
    {
        return new class implements FingerprintContributor {
            public string $current = 'v1';

            public function key(): string
            {
                return 'fp';
            }

            public function value(): string
            {
                return $this->current;
            }
        };
    }

    private function messenger(): Messenger
    {
        return new MessengerHub(new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        });
    }

    private function warned(Messenger $messenger, string $reason): bool
    {
        foreach ($messenger->getMessages()->all() as $message) {
            if (
                $message->channel === Messenger::CHANNEL_STDERR
                && \str_contains($message->content, $reason)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure(string): void $test
     */
    private function inTempDir(\Closure $test): void
    {
        $dir = \sys_get_temp_dir() . '/testo-store-' . \bin2hex(\random_bytes(6));
        \mkdir($dir, 0777, true);

        try {
            $test($dir);
        } finally {
            $this->removeDir($dir);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @\rmdir($item->getPathname()) : @\unlink($item->getPathname());
        }
        @\rmdir($dir);
    }
}
