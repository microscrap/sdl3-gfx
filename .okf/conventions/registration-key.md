---
type: Convention
title: Registration key sdl3
description: Factory driver key stays sdl3 on the Deferred lane for discovery stability.
tags: [convention, sdl3, factory, key, deferred]
generated: { by: cursor-agent/grok-4.5, at: "2026-08-08T23:20:00Z" }
status: draft
sources:
  - id: sp
    resource: src/Providers/SDL3GfxServiceProvider.php
    title: SDL3GfxServiceProvider
---

# Rule

Keep the framebuffer registration string **`sdl3`**. Class name `Sdl3Framebuffer` and namespace `Microscrap\GFX\SDL3` stay stable.

Use tubes **`extendDeferred`** (not `extendManaged`, not 0.6 Fabricate `extend(width, height, FormatSpec)`).

Publish tag: `tubes-framebuffers-sdl3` → `config/framebuffers/sdl3.php`.

# Related

- [SDL3GfxServiceProvider](../core/service-provider.md)
