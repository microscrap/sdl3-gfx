# microscrap/sdl3-gfx

[![Latest Version on Packagist](https://img.shields.io/packagist/v/microscrap/sdl3-gfx.svg)](https://packagist.org/packages/microscrap/sdl3-gfx)
[![License](https://img.shields.io/packagist/l/microscrap/sdl3-gfx.svg)](LICENSE)

SDL3 companion for ScrapyardIO **tubes 0.7** — registers the tubes framebuffer driver key `sdl3` as a **Deferred** (`Sdl3Framebuffer`), window slug `sdl3` (`SDL3WindowHandler`), and DrawingAPI (`SDL3Renderer2D`).

Default construction is **headless**: an off-screen SDL soft surface + software renderer via [`microscrap/sdl3`](https://packagist.org/packages/microscrap/sdl3) (no window). Soft Managed drivers (`full` / `dirty` / `page`) stay in tubes.

Ecosystem docs: [`0.7.x`](https://scrapyard-io.projectsaturnstudios.com/ecosystem/microscrap/sdl3-gfx/0.7.x/overview).

## Requirements

* PHP 8.4+ (`^8.4|^8.5|^8.6`)
* **ext-sdl3** ^0.5.0 — [php-io-extensions/sdl3](https://github.com/php-io-extensions/sdl3)
* [`microscrap/sdl3`](https://packagist.org/packages/microscrap/sdl3) ^0.7.0
* [`scrapyard-io/tubes`](https://packagist.org/packages/scrapyard-io/tubes) ^0.7.0 (Framebuffers + Windows)

## Installation

```bash
php -m | grep sdl3
composer require microscrap/sdl3-gfx
php workshop package:discover
# optional config stubs:
php workshop vendor:publish --tag=tubes-framebuffers-sdl3
php workshop vendor:publish --tag=tubes-windows-sdl3
# or: php workshop install:gfx sdl3
```

## What it registers (0.7)

| Registry key | Role |
|---|---|
| Framebuffer `sdl3` | `Sdl3Framebuffer` — **Deferred**; default headless SDL soft surface |
| Window `sdl3` | `SDL3WindowHandler` — live SDL window + renderer present; `setVsync` → `SDL_SetRenderVSync` (1 / DISABLED). VSync OFF + Uncapped must be allowed to exceed the panel. |

DrawingAPI: `SDL3Renderer2D` (tubes `Renderer2D` + `DrawsText`). Fabricate `gfx` registry registration remains deferred until Rendering restores.

## Create a buffer

```php
use ScrapyardIO\Tubes\Core\MagicAliases\Framebuffer;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;

$buffer = Framebuffer::driver('sdl3')
    ->size(320, 240)
    ->format(Sdl3Framebuffer::rgbaSpec())
    ->create(); // DeferredFramebuffer — SDL owns pixels
```

## Open a window

```php
use ScrapyardIO\Tubes\Core\MagicAliases\Window;
use Microscrap\GFX\SDL3\SDL3Renderer2D;

$window = Window::driver('sdl3')->title('SDL3')->size(800, 600)->open();
$gfx = (new SDL3Renderer2D)->setFramebuffer($window->framebuffer());
$gfx->fill(0xFF203040)->fillCircle(400, 300, 80, 0xE0E0E0FF);
$window->present()->pollEvents();
$window->close();
```

## Stack overview

```text
ext-sdl3
  └── microscrap/sdl3             (bindings — Surface/Render API)
        └── microscrap/sdl3-gfx   (Deferred + Window + Renderer2D)  ← this package
              └── scrapyard-io/tubes Framebuffers / Windows
```

## License

MIT
