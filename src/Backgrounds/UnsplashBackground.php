<?php

namespace EduLazaro\Laracards\Backgrounds;

use EduLazaro\Laracards\Contracts\Background;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads a photo from Unsplash once and caches it on disk.
 *
 * The cache key is the query plus the orientation, so re-running the generate
 * command does not hit the API again, and the same query always yields the
 * same card. Deleting the cached file is how you ask for a different photo.
 */
class UnsplashBackground implements Background
{
    public function __construct(
        private string $query,
        private string $cachePath,
        private ?string $accessKey = null,
        private string $orientation = 'landscape',
        private int $width = 1600,
    ) {
    }

    public function resolve(): ?string
    {
        $cached = rtrim($this->cachePath, '/') . '/' . $this->cacheKey() . '.jpg';

        if (is_file($cached)) {
            return $cached;
        }

        if (! $this->accessKey) {
            Log::warning('Laracards: no Unsplash access key configured, skipping background.', [
                'query' => $this->query,
            ]);

            return null;
        }

        if (! is_dir(dirname($cached))) {
            mkdir(dirname($cached), 0775, true);
        }

        $search = Http::withHeaders([
            'Authorization' => 'Client-ID ' . $this->accessKey,
            'Accept-Version' => 'v1',
        ])->timeout(20)->get('https://api.unsplash.com/search/photos', [
            'query' => $this->query,
            'orientation' => $this->orientation,
            'per_page' => 1,
        ]);

        if (! $search->successful()) {
            Log::warning('Laracards: Unsplash search failed.', [
                'query' => $this->query,
                'status' => $search->status(),
            ]);

            return null;
        }

        $url = data_get($search->json(), 'results.0.urls.raw');

        if (! $url) {
            Log::warning('Laracards: Unsplash returned no results.', ['query' => $this->query]);

            return null;
        }

        $photo = Http::timeout(60)->get($url . '&w=' . $this->width . '&q=80&fm=jpg&fit=max');

        if (! $photo->successful()) {
            return null;
        }

        file_put_contents($cached, $photo->body());

        return $cached;
    }

    public function fingerprint(): string
    {
        return 'unsplash:' . $this->cacheKey();
    }

    private function cacheKey(): string
    {
        return substr(sha1($this->query . '|' . $this->orientation . '|' . $this->width), 0, 20);
    }
}
