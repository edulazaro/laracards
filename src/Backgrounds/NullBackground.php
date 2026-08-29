<?php

namespace EduLazaro\Laracards\Backgrounds;

use EduLazaro\Laracards\Contracts\Background;

/** No background layer: the template paints its own. */
class NullBackground implements Background
{
    public function resolve(): ?string
    {
        return null;
    }

    public function fingerprint(): string
    {
        return 'none';
    }
}
