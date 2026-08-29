<?php

namespace EduLazaro\Laracards\Support;

/**
 * Remembers the fingerprint of every card that was generated.
 *
 * Without this, "has this card changed?" can only be answered by "does the
 * file exist?", which is why a card with the publish date printed on it has to
 * be deleted by hand whenever the date moves.
 */
class Manifest
{
    /** @var array<string,string> */
    private array $entries = [];

    private bool $loaded = false;

    public function __construct(private string $path)
    {
    }

    public function isStale(string $key, string $fingerprint, string $outputPath): bool
    {
        $this->load();

        if (! is_file($outputPath)) {
            return true;
        }

        return ($this->entries[$key] ?? null) !== $fingerprint;
    }

    public function put(string $key, string $fingerprint): void
    {
        $this->load();
        $this->entries[$key] = $fingerprint;
    }

    public function save(): void
    {
        if (! $this->loaded) {
            return;
        }

        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0775, true);
        }

        ksort($this->entries);

        file_put_contents(
            $this->path,
            json_encode($this->entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (! is_file($this->path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);
        $this->entries = is_array($decoded) ? $decoded : [];
    }
}
