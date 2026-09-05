<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Store;

use Internal\Path;
use Testo\Common\Messenger;
use Testo\Common\Store\StoreDefinition;
use Testo\Core\Log\Level;

/**
 * A store backed by a single JSON file.
 *
 * The file holds an envelope — `layout`, `name`, `schema`, `fingerprint`, `updatedAt`, `payload` —
 * so a read can reject anything that no longer matches the definition. Writes are atomic (temp file
 * in the same directory, then rename), so a reader never sees a half-written file. {@see update()}
 * serializes read-modify-write with an advisory lock. Every I/O failure is a swallowed diagnostic,
 * never a thrown error — a store must not be able to fail a run.
 *
 * @internal
 */
final class FileStore implements \Testo\Common\Store
{
    /**
     * Envelope format version, owned by the framework. An unfamiliar layout makes the file count as
     * absent — old files never get misread after the envelope shape changes.
     */
    private const LAYOUT = 1;

    /**
     * Fingerprint values, captured once per opened store: contributors describe the environment,
     * which is stable within a run, and re-evaluating them per call would re-hash files repeatedly.
     *
     * @var array<string, string>|null
     */
    private ?array $fingerprint = null;

    /**
     * Reasons already reported, so a repeatedly touched store diagnoses each problem once.
     *
     * @var array<string, true>
     */
    private array $warned = [];

    /**
     * @param float $lockTimeout Seconds to wait for the update lock before giving up and skipping
     *        the write.
     */
    public function __construct(
        private readonly Path $baseDir,
        private readonly Path $path,
        private readonly StoreDefinition $definition,
        private readonly Messenger $messenger,
        private readonly float $lockTimeout = 5.0,
    ) {}

    #[\Override]
    public function load(): ?array
    {
        $file = (string) $this->path;
        if (!\is_file($file)) {
            return null;
        }

        $raw = @\file_get_contents($file);
        if ($raw === false) {
            $this->warn('unreadable');
            return null;
        }

        return $this->decode($raw);
    }

    #[\Override]
    public function save(array $payload): void
    {
        $this->writeAtomic($this->envelope($payload));
    }

    #[\Override]
    public function update(\Closure $fn): void
    {
        if (!$this->ensureDir()) {
            return;
        }

        $handle = @\fopen((string) $this->path . '.lock', 'c');
        if ($handle === false) {
            $this->warn('lock unavailable');
            return;
        }

        try {
            if (!$this->acquire($handle)) {
                $this->warn('lock timeout');
                return;
            }

            $next = $fn($this->load());
            $next === null or $this->writeAtomic($this->envelope($next));
        } finally {
            @\flock($handle, \LOCK_UN);
            @\fclose($handle);
        }
    }

    #[\Override]
    public function delete(): void
    {
        @\unlink((string) $this->path);
        @\unlink((string) $this->path . '.lock');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        try {
            /** @var mixed $data */
            $data = \json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->warn('corrupted');
            return null;
        }

        if (
            !\is_array($data)
            || ($data['layout'] ?? null) !== self::LAYOUT
            || ($data['name'] ?? null) !== $this->definition->name
        ) {
            $this->warn('corrupted');
            return null;
        }

        if (($data['schema'] ?? null) !== $this->definition->schema) {
            $this->warn('schema mismatch');
            return null;
        }

        $stored = $data['fingerprint'] ?? null;
        $current = $this->fingerprint();
        \is_array($stored) and \ksort($stored);
        \ksort($current);
        if ($stored !== $current) {
            $this->warn('fingerprint drift');
            return null;
        }

        $payload = $data['payload'] ?? null;
        if (!\is_array($payload)) {
            $this->warn('corrupted');
            return null;
        }

        return $payload;
    }

    /**
     * @param array<array-key, mixed> $payload
     * @return array<string, mixed>
     */
    private function envelope(array $payload): array
    {
        return [
            'layout' => self::LAYOUT,
            'name' => $this->definition->name,
            'schema' => $this->definition->schema,
            'fingerprint' => $this->fingerprint(),
            'updatedAt' => \gmdate(\DATE_ATOM),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fingerprint(): array
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $result = [];
        foreach ($this->definition->fingerprint as $contributor) {
            $result[$contributor->key()] = $contributor->value();
        }

        return $this->fingerprint = $result;
    }

    /**
     * @param array<string, mixed> $envelope
     */
    private function writeAtomic(array $envelope): void
    {
        if (!$this->ensureDir()) {
            return;
        }

        try {
            $json = \json_encode($envelope, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            $this->warn('encode failed: ' . $e->getMessage());
            return;
        }

        $dir = (string) $this->path->parent();
        $tmp = $dir . '/' . \uniqid('.store-', true) . '.tmp';

        if (@\file_put_contents($tmp, $json, \LOCK_EX) === false || !@\rename($tmp, (string) $this->path)) {
            @\unlink($tmp);
            $this->warn('write failed');
        }
    }

    private function ensureDir(): bool
    {
        if (!$this->ensureBase()) {
            return false;
        }

        $dir = (string) $this->path->parent();
        if (\is_dir($dir) || @\mkdir($dir, 0777, true) || \is_dir($dir)) {
            return true;
        }

        $this->warn('directory unavailable');
        return false;
    }

    /**
     * Create the base directory on first write and mark it self-ignoring — the store must never be
     * committed. A missing .gitignore is not worth failing a run over, so its write is best-effort.
     */
    private function ensureBase(): bool
    {
        $base = (string) $this->baseDir;
        if (!\is_dir($base) && !@\mkdir($base, 0777, true) && !\is_dir($base)) {
            $this->warn('directory unavailable');
            return false;
        }

        $gitignore = $base . '/.gitignore';
        \is_file($gitignore) or @\file_put_contents($gitignore, "*\n");

        return true;
    }

    /**
     * @param resource $handle
     */
    private function acquire($handle): bool
    {
        $deadline = \microtime(true) + $this->lockTimeout;
        do {
            if (@\flock($handle, \LOCK_EX | \LOCK_NB)) {
                return true;
            }
            \usleep(20_000);
        } while (\microtime(true) < $deadline);

        return false;
    }

    private function warn(string $reason): void
    {
        if (isset($this->warned[$reason])) {
            return;
        }

        $this->warned[$reason] = true;
        $this->messenger->log(
            Messenger::CHANNEL_STDERR,
            \sprintf('Store "%s" skipped: %s (%s).', $this->definition->name, $reason, $this->path),
            Level::Warning,
        );
    }
}
