---
type: Core
title: SDL3Renderer2D
description: "tubes Renderer2D DrawingAPI for Sdl3Framebuffer — fill via renderClear; primitives via setPixel/setSegment."
resource: src/SDL3Renderer2D.php
tags: [core, rendering, renderer2d, sdl3]
generated: { by: "cursor-agent/grok-4.5", at: "2026-08-09T06:10:00Z" }
status: draft
---

# Role

`Microscrap\GFX\SDL3\SDL3Renderer2D` implements tubes `DrawingAPI` on a borrowed `Sdl3Framebuffer`.

| Method family | Path |
|---------------|------|
| `fill()` | `$framebuffer->fill()` → `SDL_RenderClear` |
| pixels / segments / lines / shapes | `setPixel` / `setSegment` (SDL render points/rects) |
| text | tubes `DrawsText` (ClassicFont + GFXFont registry) |

Present stays on the WindowHandler / `Sdl3Framebuffer::present()` path (`SDL_RenderPresent`).

# Related

- [Sdl3Framebuffer](sdl3-framebuffer.md)
- [SDL3WindowHandler](sdl3-window-handler.md)
