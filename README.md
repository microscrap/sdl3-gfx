# microscrap/sdl3-gfx

[![Latest Version on Packagist](https://img.shields.io/packagist/v/microscrap/sdl3-gfx.svg)](https://packagist.org/packages/microscrap/sdl3-gfx)
[![License](https://img.shields.io/packagist/l/microscrap/sdl3-gfx.svg)](LICENSE)

SDL3-native rendering for the ScrapyardIO Framework.

This package registers the `sdl3` framebuffer strategy and the `sdl3` GFX render driver so Fabricate can draw with LibSDL3 instead of (or alongside) `phpdafruit`.

It is the rendering half of the SDL3 desktop stack. For an actual windowed display panel, install [`dept-of-scrapyard-robotics/sdl3-display`](https://packagist.org/packages/dept-of-scrapyard-robotics/sdl3-display).

## Requirements

* PHP 8.3+
* **ext-sdl3** ^0.5.0 — [php-io-extensions/sdl3](https://github.com/php-io-extensions/sdl3)
* [`microscrap/sdl3`](https://packagist.org/packages/microscrap/sdl3) ^0.5.0
* ScrapyardIO Framework 0.6 (`fabricate/framebuffers`, `fabricate/rendering`, …)

## Installation

Confirm the extension is loaded:

```bash
php -m | grep sdl3
```

### Via Composer

```bash
composer require microscrap/sdl3-gfx
```

Package discovery registers `Microscrap\GFX\SDL3\Providers\SDL3GfxServiceProvider` automatically.

### Via Workshop (recommended in a Scrapyard app)

From a ScrapyardIO application:

```bash
workshop install:gfx --sdl3
```

Or interactively:

```bash
workshop install:gfx
```

That installs this package (and optional siblings), then can activate SDL3 as the default rendering backend.

## What it registers

| Registry key | Role |
|---|---|
| Framebuffer `sdl3` | `Sdl3Framebuffer` |
| Renderer `sdl3` | `SDL3GFXRenderDriver` |

Typical `config/gfx.php` after activation:

```php
return [
    'rendering' => [
        'default' => 'sdl3',
        'engines' => [
            'sdl3' => [],
            // ...
        ],
    ],
];
```

## Pair with a windowed display

This package alone does not open a desktop window. Once GFX is installed, Workshop exposes:

```bash
workshop install:sdl3-display
```

That command is **hidden** when `dept-of-scrapyard-robotics/sdl3-display` is already required. When visible, it:

1. `composer require dept-of-scrapyard-robotics/sdl3-display:^0.6.0`
2. Runs `workshop config:sdl3-display` to add a default `windowed.sdl3` entry

Options:

```bash
workshop install:sdl3-display
workshop install:sdl3-display --force
workshop install:sdl3-display --composer=/path/to/composer
```

## Manual wiring (without install:gfx)

```bash
composer require microscrap/sdl3-gfx
php workshop package:discover
```

Then set `config/gfx.php` `rendering.default` to `sdl3` (or keep `phpdafruit` and select `sdl3` per display).

## Stack overview

```text
ext-sdl3
  └── microscrap/sdl3          (bindings)
        └── microscrap/sdl3-gfx          (framebuffer + renderer)  ← this package
              └── dept-of-scrapyard-robotics/sdl3-display  (windowed panel)
```

## License

MIT
