---
type: Core
title: SDL3 VSync
description: "setVsync — SDL_SetRenderVSync 1 vs DISABLED on Darwin and Linux."
resource: src/SDL3WindowHandler.php
tags: [core, vsync, sdl3]
generated: { by: "cursor-agent/grok-4.6", at: "2026-08-12T19:35:00Z" }
status: draft
---

# Role

`SDL3WindowHandler::setVsync(bool)` maps onto `SDL_SetRenderVSync`:

| Request | SDL |
|---------|-----|
| on | `1` (tear-free) |
| off | `SDL_RENDERER_VSYNC_DISABLED` |

Same contract on Darwin and Linux. Hardware vsync is a **floor at the panel refresh**. Sleep in the app (`FramePacer`) can only **cap**.

VSync OFF + Uncapped must be allowed to exceed the panel refresh. Verified 2026-08-12 on a 120 Hz Mac: present ~0.15 ms, delivered **210–324 Hz**. Default remains off for non-Tetriminos sketches.

# Related

- [SDL3WindowHandler](sdl3-window-handler.md)
- [Sdl3Framebuffer](sdl3-framebuffer.md)
