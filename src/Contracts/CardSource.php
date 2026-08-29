<?php

namespace EduLazaro\Laracards\Contracts;

use EduLazaro\Laracards\Card;

/**
 * Maps a collection of content (blog posts, tools, cities) to cards.
 *
 * Implement one per kind of content and register it under `sources` in the
 * config. The generate command walks every source it is given.
 */
interface CardSource
{
    /**
     * @return iterable<Card>
     */
    public function cards(): iterable;
}
