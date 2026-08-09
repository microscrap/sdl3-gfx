---
type: Trap
title: Do not resolve gfx / Rendering
description: callAfterResolving('gfx', …) fails until Fabricate Rendering is restored — leave SDL3Gfx unregistered.
tags: [trap, gfx, rendering, 0.7]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-08T23:15:00Z" }
status: draft
sources:
  - id: sp
    resource: src/Providers/SDL3GfxServiceProvider.php
    title: SDL3GfxServiceProvider
---

# Trap

0.6 registered `callAfterResolving('gfx', fn (RenderManager) => …)`. In 0.7 that binding is gone; resolving it fatals boot.

# Do

Register **only** the framebuffer factory this pass. Keep `SDL3Gfx` / `SDL3GFXRenderDriver` in the tree but unregistered. Narrow Arch tests that expect `Renderer2D`.

# Related

- [SDL3GfxServiceProvider](../core/service-provider.md)
