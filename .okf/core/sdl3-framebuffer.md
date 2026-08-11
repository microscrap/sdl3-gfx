---
type: Core
title: Sdl3Framebuffer
description: Tubes DeferredFramebuffer abstract for driver key sdl3 — SDL owns pixels; default headless soft surface via microscrap/sdl3.
tags: [core, framebuffer, deferred, sdl3, headless]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-09T03:40:00Z" }
status: draft
sources:
  - id: fb
    resource: src/Sdl3Framebuffer.php
    title: Sdl3Framebuffer
  - id: deferred
    resource: ../../scrapyard-io/tubes/src/Tubes/Framebuffers/DeferredFramebuffer.php
    title: DeferredFramebuffer abstract
---

# Role

`Microscrap\GFX\SDL3\Sdl3Framebuffer` **extends** `ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer`.[^fb]

**SDL owns the pixels** — not a tubes `PixelStore`. Soft Managed drivers (`full` / `dirty` / `page`) are a different lane.

## Default = headless (no window)

`::sized($w, $h, $hostFormat)` / ctor without attach:

1. `Init::init(0)` via microscrap/sdl3
2. Off-screen `Surface::createSurface(…, RGBA8888)`
3. `Render::createSoftwareRenderer($surface->ptr)`

No OS window. Works under dummy/headless CI.

## Windowed

`attachedTo(SDLRenderer, FormatSpec, w, h)` borrows a live renderer (never destroyed by this class). `flush` presents in place and does not full-frame dump (avoid OOM on large windowed frames).

## PanelIC / FormatSpec flush (headless)

Host is always SDL RGBA8888 (`rgbaSpec()`). `PanelIC::present()` flushes the IC FormatSpec (e.g. ST7789 RGB565):

- Dirty rects tracked on draw; coalesced at flush
- `deferDirty()` unions many primitive marks into one bbox (fillCircle hlines)
- Whole-surface dirty → `RenderType::FULL`
- Sparse dirty → `RenderType::PARTIAL` (prefer `Render::renderReadPixels($renderer, $rect)`; else one shared surface read + slice)
- Matching B32 / ROW_MAJOR B16 → **chunked `pack()`** (not per-pixel `PixelStore` — that crushed Pi0 FPS)
- Other foreign specs still pack via `PixelStore`
- `damageGranularity()` is **pixel** when headless (so UX Scene / PanelIC partial path engages)

# Related

- [SDL3GfxServiceProvider](service-provider.md) — `extendDeferred('sdl3', …)` + publish `tubes-framebuffers-sdl3`
- [Window attach](../traps/window-attach-deferred.md) — windowed path via `attachedTo`

[^fb]: Sdl3Framebuffer
