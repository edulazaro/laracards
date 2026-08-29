<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Renderer
    |--------------------------------------------------------------------------
    |
    | Which binary turns the composed SVG into a raster image. "rsvg" uses
    | rsvg-convert (Debian/Ubuntu package librsvg2-bin). "resvg" uses the Rust
    | binary, which has better SVG2 support and can be pointed at font files
    | directly, which matters when the container has no brand fonts installed.
    |
    */

    'renderer' => env('LARACARDS_RENDERER', 'rsvg'),

    'renderers' => [
        'rsvg' => [
            'binary' => env('LARACARDS_RSVG_BINARY', 'rsvg-convert'),
        ],
        'resvg' => [
            'binary' => env('LARACARDS_RESVG_BINARY', 'resvg'),
            // Passed as --font-file, so the card renders with your typography
            // even on a machine where the font is not installed system-wide.
            'font_files' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    */

    'width' => 1200,
    'height' => 630,

    'paths' => [
        'templates' => resource_path('cards'),
        'output' => public_path('img/cards'),
        'temp' => storage_path('app/laracards/tmp'),
        'manifest' => storage_path('app/laracards/manifest.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fonts
    |--------------------------------------------------------------------------
    |
    | Used to MEASURE text, not to render it. Text fitting reads the real glyph
    | widths through GD, so a title full of wide words no longer overflows the
    | way a character-count estimate does. Point these at the same faces your
    | SVG templates declare, or the measurement will drift from the render.
    |
    */

    'fonts' => [
        'default' => public_path('fonts/Lato-Bold.ttf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backgrounds
    |--------------------------------------------------------------------------
    |
    | A background is just another SVG layer, embedded as a data URI exactly
    | like the logo. That is what lets the same template take a flat colour, an
    | Unsplash photo or a PNG you generated elsewhere with no template changes.
    |
    */

    'backgrounds' => [
        'unsplash' => [
            'access_key' => env('UNSPLASH_ACCESS_KEY'),
            'cache' => storage_path('app/laracards/backgrounds'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Templates
    |--------------------------------------------------------------------------
    |
    | Each template is an SVG file with {{placeholders}}. A "fit" block turns
    | one long string into wrapped <tspan> lines plus a font size that fits,
    | exposed as {{key_tspans}} and {{key_font_size}}.
    |
    */

    'templates' => [

        'post' => [
            'file' => 'post.svg',
            'fit' => [
                'title' => [
                    'font' => 'default',
                    'x' => 80,
                    'max_width' => 1040,
                    'max_lines' => 3,
                    'sizes' => [82, 72, 64, 56, 48],
                    'line_height' => 1.17,
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Each source maps a content collection to cards. This is what replaces the
    | eight near-identical artisan commands: one command, one class per kind of
    | content, implementing EduLazaro\Laracards\Contracts\CardSource.
    |
    */

    'sources' => [
        // 'post' => App\Cards\BlogPostCards::class,
    ],

];
