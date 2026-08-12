---
type: Orientation
title: Package (0.7)
description: microscrap/sdl3-gfx 0.7.0 — SDL3 companion; registers Deferred framebuffer driver sdl3 via tubes.
resource: .
tags: [orientation, sdl3-gfx, microscrap, tubes, 0.7, deferred]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-08T23:20:00Z" }
status: draft
sources:
  - id: composer
    resource: composer.json
    title: Package name, version, PHP, providers, deps
  - id: sp
    resource: src/Providers/SDL3GfxServiceProvider.php
    title: SDL3GfxServiceProvider
---

# What it is

Composer package `microscrap/sdl3-gfx` at **0.7.0** — ScrapyardIO companion that registers tubes framebuffer **`sdl3`** (`Sdl3Framebuffer`), window slug **`sdl3`** (`SDL3WindowHandler`), and DrawingAPI (`SDL3Renderer2D`).[^composer]

| Field | Value |
|-------|-------|
| Name | `microscrap/sdl3-gfx` |
| Version | `0.7.0` |
| PHP | `^8.4\|^8.5\|^8.6`[^composer] |
| Namespace | `Microscrap\GFX\SDL3\` → `src/`[^composer] |
| Discovery | `extra.scrapyard-io.providers` → `SDL3GfxServiceProvider`[^composer] |
| Role | Deferred `Sdl3Framebuffer` + live `SDL3WindowHandler` + `SDL3Renderer2D` |

Requires:

- `ext-sdl3` `^0.5.0`
- `microscrap/sdl3` `^0.7.0` (bindings API to SDL surfaces/renderers)
- `scrapyard-io/tubes` `^0.7.0` (umbrella — includes Framebuffers + Windows; split `tubes/windows` not published yet)
- `fabricate/nuts-and-bolts` `^0.7.0`

# Lanes

| Lane | Drivers |
|------|---------|
| Managed (tubes built-ins) | `full`, `dirty`, `page` — soft `PixelStore` |
| Deferred (this package) | `sdl3` — SDL host buffer; headless soft surface or window `attachedTo` |
| Window (this package) | `sdl3` — `SDL3WindowHandler`; `setVsync` → `SDL_SetRenderVSync` (1 / DISABLED) |

# Related

| Topic | Concept |
|-------|---------|
| Provider | [SDL3GfxServiceProvider](../core/service-provider.md) |
| Buffer | [Sdl3Framebuffer](../core/sdl3-framebuffer.md) |
| VSync | [SDL3 VSync](../core/vsync.md) |
| Bindings | `microscrap/sdl3` |

[^composer]: Package name, version, PHP, providers, deps
