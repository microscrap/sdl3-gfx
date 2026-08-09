---
type: Trap
title: Window attach vs headless
description: Default Sdl3Framebuffer is headless SDL soft surface; WindowHandler / attachedTo borrows a window renderer — do not confuse with Managed PixelStore.
tags: [trap, window, attachedTo, deferred, headless]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-08T23:20:00Z" }
status: draft
sources:
  - id: fb
    resource: src/Sdl3Framebuffer.php
    title: Sdl3Framebuffer
---

# Trap

**Headless ≠ Managed.** Headless means “no OS window” while SDL still owns an off-screen surface + software renderer. Soft PHP `PixelStore` buffers are the Managed lane (`full` / `dirty` / `page`).

# Do

- Default / `::sized()` → headless deferred (soft surface).
- Window present path → `attachedTo($sdlRenderer, …)` so draws go to the window’s renderer; `flush` presents, does not dump the whole frame into PHP.
- Never register `sdl3` with `extendManaged`.

# Related

- [Sdl3Framebuffer](../core/sdl3-framebuffer.md)
