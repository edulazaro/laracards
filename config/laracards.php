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

            // Extra environment for the process. librsvg resolves fonts through
            // fontconfig, so a brand face that is not installed system-wide
            // falls back silently. Pointing FONTCONFIG_FILE at a config of your
            // own is how you use the fonts your project already ships without
            // rebuilding the container image.
            // 'env' => ['FONTCONFIG_FILE' => resource_path('cards/fonts.conf')],
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

    // Defaults for every template. A template can override any of the three,
    // which is how one project serves a 1200x630 open graph card and a square
    // social card from the same command.
    'width' => 1200,
    'height' => 630,

    // png, jpg or webp. PNG keeps flat cards crisp and lossless; jpg is a
    // fraction of the size once a photograph is behind the text. The renderers
    // only write PNG, so anything else is converted with GD afterwards.
    'format' => 'png',
    'quality' => 85,

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
    | A template can also be a PNG, JPG or WebP. Then the text is drawn onto
    | the image with GD, no binary needed, and each fit block also takes
    | 'color' and 'align', plus a 'baseline' or a place in a 'stack'.
    |
    */

    'templates' => [

        'post' => [
            'file' => 'post.svg',
            // 'width' => 1200,
            // 'height' => 630,
            // 'format' => 'jpg',
            'fit' => [
                'title' => [
                    'font' => 'default',
                    'x' => 80,
                    'max_width' => 1040,
                    'max_lines' => 3,
                    'sizes' => [82, 72, 64, 56, 48],
                    'line_height' => 1.17,

                    // Where the block sits. With 'anchor' => 'bottom' the LAST
                    // line lands on 'baseline', so a one-line headline and a
                    // three-line one stay visually balanced; the default 'top'
                    // puts the FIRST line there. Either way the template reads
                    // the computed value from {{title_baseline}}.
                    'anchor' => 'top',
                    'baseline' => 320,
                ],
            ],
        ],

        // An image template: the design is the image, the text is drawn on it.
        // 'og' => [
        //     'file' => 'og.png',
        //     'stack' => ['fields' => ['title', 'subtitle'], 'center_y' => 315, 'gap' => 34],
        //     'fit' => [
        //         'title' => ['font' => 'default', 'x' => 600, 'max_width' => 900, 'max_lines' => 3,
        //                     'sizes' => [56, 48, 44], 'line_height' => 1.2,
        //                     'color' => 'rgba(255,255,255,0.9)', 'align' => 'center'],
        //         'subtitle' => ['font' => 'default', 'x' => 600, 'max_width' => 1000, 'max_lines' => 1,
        //                        'sizes' => [26], 'line_height' => 1.0,
        //                        'color' => 'rgba(255,255,255,0.45)', 'align' => 'center'],
        //     ],
        // ],

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
