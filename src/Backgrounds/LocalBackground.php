<?php

namespace EduLazaro\Laracards\Backgrounds;

use EduLazaro\Laracards\Contracts\Background;

/**
 * A background already on disk.
 *
 * This is the driver for images generated elsewhere, including anything you
 * produced with an image model and dropped into the repository: save the file,
 * point at it, done. The template does not change.
 */
class LocalBackground implements Background
{
    public function __construct(private string $path)
    {
    }

    public function resolve(): ?string
    {
        return is_file($this->path) ? $this->path : null;
    }

    public function fingerprint(): string
    {
        $resolved = $this->resolve();

        return $resolved
            ? 'local:' . substr(hash_file('xxh128', $resolved), 0, 16)
            : 'local:missing';
    }
}
