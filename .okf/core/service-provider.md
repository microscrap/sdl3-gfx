---
type: Core
title: SDL3GfxServiceProvider
description: Package discovery provider; boot registers extendDeferred('sdl3'), WindowFactory::extend('sdl3'), and publish stubs.
tags: [core, provider, framebuffer, window, sdl3, deferred]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T04:20:00Z" }
status: draft
sources:
  - id: sp
    resource: src/Providers/SDL3GfxServiceProvider.php
    title: SDL3GfxServiceProvider
---

# Role

`Microscrap\GFX\SDL3\Providers\SDL3GfxServiceProvider` is registered via Composer `extra.scrapyard-io.providers`.[^sp]

`register()` is empty this pass (no console command binding).

`boot()` publishes (console) and registers both lanes:

```php
$this->publishes([/* … */], 'tubes-framebuffers-sdl3');
$this->publishes([/* … */], 'tubes-windows-sdl3');

$framebuffers->extendDeferred(
    'sdl3',
    fn (PendingFramebuffer $pending) => Sdl3Framebuffer::sized(
        $pending->widthValue(),
        $pending->heightValue(),
        $pending->hostFormatValue(),
    ),
);

$windows->extend('sdl3', SDL3WindowHandler::class);
```

**Deferred lane** — not `extendManaged`. Managed is for soft `PixelStore` strategies (`full` / `dirty` / `page`).

# Not in this pass

- No `$this->program->singleton` / `InstallSdl3DisplayCommand` registration.
- No `callAfterResolving('gfx', …)` — see [Do not resolve gfx / Rendering](../traps/no-gfx-registry.md).
# Related

- [Sdl3Framebuffer](sdl3-framebuffer.md)
- [SDL3WindowHandler](sdl3-window-handler.md)
- [SDL3Renderer2D](sdl3-renderer-2d.md)
- [Registration key sdl3](../conventions/registration-key.md)

[^sp]: SDL3GfxServiceProvider
