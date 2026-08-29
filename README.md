![Laracards](art/banner.png)

# Laracards for Laravel: editorial social cards and blog covers

<p align="center">
    <a href="https://github.com/edulazaro/laracards/actions/workflows/tests.yml"><img src="https://github.com/edulazaro/laracards/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
    <a href="https://packagist.org/packages/edulazaro/laracards"><img src="https://img.shields.io/packagist/v/edulazaro/laracards" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/edulazaro/laracards"><img src="https://img.shields.io/packagist/dt/edulazaro/laracards" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/laracards"><img src="https://img.shields.io/packagist/php-v/edulazaro/laracards" alt="PHP Version"></a>
    <a href="https://github.com/edulazaro/laracards/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/edulazaro/laracards" alt="License"></a>
</p>

Editorial social cards and blog covers for Laravel. SVG templates rendered with `rsvg-convert` or `resvg`, text fitted with real font metrics, and backgrounds that can be a flat colour, an Unsplash photo or an image you generated somewhere else.

```bash
composer require edulazaro/laracards
```

You also need one renderer binary on the machine:

```bash
apt install librsvg2-bin        # rsvg-convert, the default
# or grab resvg, a self-contained binary with better font control
```

## Why

Generating an OG card usually ends up one of two ways: a headless browser, which drags Node and a few hundred megabytes into every deploy, or a hand-rolled image script, which is fine until you want a photo behind the text.

Laracards composes the card as an SVG and shells out to a single binary. A background image is just another SVG layer embedded as a data URI, which is what lets one template serve a flat card, a stock photo and an AI-generated image without changing.

## Publish the config and the example template

```bash
php artisan vendor:publish --tag=laracards-config
php artisan vendor:publish --tag=laracards-templates
```

## A card

```php
use EduLazaro\Laracards\Card;

Card::make('why-agents-are-more-than-a-model')
    ->template('post')
    ->data([
        'category_label' => 'DESARROLLO',
        'title' => 'Por qué un agente de IA es mucho más que llamar a un modelo',
        'author_name' => 'Edu Lazaro',
        'date_formatted' => '7 de agosto, 2026',
        'brand_label' => 'ANDORRADEV.COM',
        'logo_uri' => DataUri::fromFile(public_path('img/logo.png')),
    ])
    ->output(public_path('img/blog/why-agents.png'))
    ->generate();
```

`title` is declared as a fit block in the config, so the generator turns it into `{{title_tspans}}` and `{{title_font_size}}` for the template.

## Backgrounds

Three drivers, one code path. All of them end as a local file embedded through `__BACKGROUND_URI__`.

```php
$card->background(null);                              // template paints its own
$card->background(public_path('img/ai/hero.png'));    // anything already on disk
$card->unsplash('mountain fog');                      // downloaded once, then cached
```

The Unsplash driver caches by query, so re-running the command does not hit the API and the same query always yields the same card. Delete the cached file to ask for a different photo.

## Sources and the command

A `CardSource` maps a content collection to cards. One per kind of content, instead of one artisan command per kind of content.

```php
class BlogPostCards implements CardSource
{
    public function __construct(private BlogService $blog) {}

    public function cards(): iterable
    {
        foreach ($this->blog->all(includeScheduled: true) as $post) {
            yield Card::make($post->slug)
                ->template('post')
                ->data([
                    'title' => $post->title,
                    'category_label' => mb_strtoupper($post->category),
                    'author_name' => $post->author,
                    'date_formatted' => $post->date->translatedFormat('j \d\e F, Y'),
                ])
                ->background($post->cover)
                ->output(public_path("img/blog/{$post->slug}.png"));
        }
    }
}
```

Register it and run:

```php
'sources' => ['post' => App\Cards\BlogPostCards::class],
```

```bash
php artisan cards:generate
php artisan cards:generate --source=post --only=some-slug --force
php artisan cards:generate --dry-run
```

## Placing a block whose height you do not know

A headline can take one line or three, and SVG cannot do arithmetic, so the position has to be computed before the template sees it. Two things come out of every fit rule.

`anchor` decides which line lands on `baseline`. The default, `top`, puts the first line there. With `bottom`, the last line does, which keeps a short headline and a long one sitting on the same rule instead of drifting down the card.

```php
'title' => [
    'font' => 'default', 'x' => 80, 'max_width' => 1040,
    'max_lines' => 3, 'sizes' => [82, 72, 64, 56, 48], 'line_height' => 1.17,
    'anchor' => 'bottom', 'baseline' => 434,
],
```

The template reads the result from `{{title_baseline}}`, and everything that has to travel with the headline goes in a group translated to it:

```xml
<g transform="translate(0 {{title_baseline}})">
  <text x="80" y="-78" font-size="19" fill="#eab308">{{category_label}}</text>
  <text x="80" y="0" font-size="{{title_font_size}}">{{title_tspans}}</text>
</g>
```

Every block also exposes `{key}_bottom`, which is where it actually ends. That is how a subtitle hangs off a headline of unknown height and keeps the same gap in every card:

```xml
<g transform="translate(0 {{title_bottom}})">
  <text x="80" y="52" font-size="{{subtitle_font_size}}">{{subtitle_tspans}}</text>
</g>
```

Alongside those, a fit rule also fills `{key}_tspans`, `{key}_font_size` and `{key}_line_count`.

## Text fitting is measured, not estimated

Wrapping by character count gives every glyph the same budget, so a title made of wide words silently runs off the card. Laracards measures with GD against the same font file, picks the largest size from the candidate list that fits in the given number of lines, and only ellipsizes when even the smallest one does not.

For a 1040px column at 82px:

| Title | Character count | Measured |
|---|---|---|
| `MMMMMMMM MMMMMMMM MMMMMMMM` | 2 lines, 1672px wide, 632px off the card | 3 lines, 818px |
| `Indemnizaciones millonarias por incumplimiento…` | 5 lines, truncated to 3 with an ellipsis | 3 lines at 56px, full title |

## Regeneration is decided by content, not by file existence

Every generated card records a fingerprint of its template, payload and background in a manifest. Change the title, move the publish date, swap the photo, and the card regenerates by itself on the next run. Nothing changes, nothing is rewritten.

This is what removes the "delete the PNG by hand because the date is printed on it" step.

## Fonts

The SVG declares its own `font-family`, so `rsvg-convert` resolves it through fontconfig: a brand face that is not installed on the machine silently falls back to something else, and the card looks different in production than on your laptop.

Two ways out. Install the face in the container, or switch to the resvg renderer and list the files:

```php
'renderer' => 'resvg',
'renderers' => [
    'resvg' => [
        'binary' => 'resvg',
        'font_files' => [public_path('fonts/Lato-Bold.ttf')],
    ],
],
```

A third way avoids touching the image at all: `rsvg-convert` reads fontconfig, and the renderer passes through whatever environment you give it.

```php
'rsvg' => [
    'binary' => 'rsvg-convert',
    'env' => ['FONTCONFIG_FILE' => resource_path('cards/fonts.conf')],
],
```

That file has to list the system font directories too, because it replaces the system configuration rather than adding to it:

```xml
<fontconfig>
  <dir prefix="relative">../../public/fonts</dir>
  <dir>/usr/share/fonts</dir>
  <cachedir prefix="relative">../../storage/app/laracards/fccache</cachedir>
</fontconfig>
```

`prefix="relative"` resolves against the config file, so the same file works on your laptop and inside a container without knowing the mount path.

Either way, keep `laracards.fonts` pointing at the same faces the templates declare, or the measurement drifts from the render.

## Generate locally, commit the output

Cards are static. Generating them on request means a render on the hot path and a binary dependency in your web container. Run the command when content changes and commit the images.

## Testing

Run the package tests with:

```
./vendor/bin/phpunit
```

The tests that exercise the renderer are skipped when no binary is installed, so a machine without `rsvg-convert` still gets a green suite for everything else.

## Contributing

Contributions are welcome! Please fork the repo, add tests, and submit a PR.

## Sponsors

Laracards is supported by the following sponsors. Thank you for keeping it growing:

<p>
  <a href="https://kenodo.com"><img src="art/logo-kenodo.png" width="24" alt="Kenodo"></a>&nbsp;<a href="https://kenodo.com">Kenodo</a>&nbsp;&nbsp;&nbsp;&nbsp;
  <a href="https://andorradev.com"><img src="art/logo-andorradev.png" width="24" alt="AndorraDev"></a>&nbsp;<a href="https://andorradev.com">AndorraDev</a>
</p>

## Author

Created by [Edu Lazaro](https://edulazaro.com)

## License

Laracards is open-sourced software licensed under the [MIT license](LICENSE.md).
