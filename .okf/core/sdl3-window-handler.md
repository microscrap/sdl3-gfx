---
type: Core
title: SDL3WindowHandler
description: "tubes WindowHandler for slug sdl3 — FormatSpec from Sdl3Framebuffer::rgbaSpec(); live SDL window+renderer + attached framebuffer."
resource: src/SDL3WindowHandler.php
tags: [core, window, handler, sdl3, deferred]
generated: { by: "cursor-agent/grok-4.5", at: "2026-08-09T06:10:00Z" }
status: draft
---

# Role

`Microscrap\GFX\SDL3\SDL3WindowHandler` extends tubes `WindowHandler` for registry slug **`sdl3`**. Host packing is fixed to [`Sdl3Framebuffer::rgbaSpec()`](sdl3-framebuffer.md).

# Lifecycle

| Hook | Behavior |
|------|----------|
| `bootNative()` | `Init::init(SDL_INIT_VIDEO)` (once), `Video::createWindowAndRenderer` |
| `bindFramebuffer()` | `Sdl3Framebuffer::attachedTo(renderer, …)` — borrowed window renderer |
| `presentNative()` | `$framebuffer->present()` → `SDL_RenderPresent` |
| `pollNative()` | Drain `Events::pollEvent`; quit / close-requested → close flag; fan out to [`SDL3InputHandler`](sdl3-input-handler.md) (`ingestEvent` then `poll`) |
| `inputHandler()` | Lazy shared `SDL3InputHandler` for `EngineInput` |
| `setVsync(bool)` | `SDL_SetRenderVSync` 1 / DISABLED. VSync OFF + Uncapped must be allowed to exceed the panel (Darwin and Linux). |
| `shouldClose()` | Close flag or null window |
| `close()` / `destroyNative()` | Close input natives, drop FB, destroy renderer + window; does **not** call `SDL_Quit` |

# Usage

```php
$window = Window::driver('sdl3')->title('SDL3')->size(800, 600)->open();
$fb = $window->framebuffer(); // Sdl3Framebuffer, isHeadless() === false
$fb->fill(0xFF203040)->setPixel(10, 10, 0xFFFFFFFF);
$window->present()->pollEvents();
$window->close();
```

Requires **ext-sdl3 ≥ 0.5.0** and `microscrap/sdl3`.

# Related

- tubes `.okf/core/window-handler.md`
- [Sdl3Framebuffer](sdl3-framebuffer.md)
- [SDL3 VSync](vsync.md)
- [SDL3InputHandler](sdl3-input-handler.md)
- [SDL3Renderer2D](sdl3-renderer-2d.md)
