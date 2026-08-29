<?php

namespace EduLazaro\Laracards\Contracts;

/**
 * Resolves to a local image file that gets embedded into the card as a data URI.
 *
 * Every driver returns the same thing, a path on disk, so a flat colour, an
 * Unsplash photo and an AI-generated PNG all travel through one code path.
 */
interface Background
{
    /** Absolute path to a local image, or null for no background layer. */
    public function resolve(): ?string;

    /** Stable identity of this background, so the manifest can detect changes. */
    public function fingerprint(): string;
}
