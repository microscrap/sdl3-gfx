---
okf_version: "0.2"
---

# microscrap/sdl3-gfx Knowledge Bundle

Package knowledge for `microscrap/sdl3-gfx` (SDL3 companion GFX for ScrapyardIO / tubes, v0.7.0).
Read this index first; open only the concepts needed for the task.

**Trust rule:** Prefer `status: stable`. Treat `deprecated` as historical only. New agent-written concepts stay `status: draft` until a human verifies them.
**Placement:** This bundle lives at the **package root** only — never under `src/`.
**Links:** Concept cross-links use paths relative to each file.
**Scope:** Document what this package registers in 0.7 (Deferred `sdl3` framebuffer + live `SDL3WindowHandler` + `SDL3Renderer2D`). Soft Managed drivers live in tubes. Do **not** claim Fabricate `gfx` registry until Rendering returns.
**Dist note:** `.okf/` and root `AGENTS.md` are already `export-ignore` in `.gitattributes`.

# Orientation

* [Package (0.7)](orientation/package.md) - Companion GFX; depends on `microscrap/sdl3` + tubes framebuffers.

# Core

* [SDL3GfxServiceProvider](core/service-provider.md) - Discovers; framebuffer + window `extend` + publish stubs.
* [Sdl3Framebuffer](core/sdl3-framebuffer.md) - Deferred SDL-owned buffer; default headless soft surface.
* [SDL3WindowHandler](core/sdl3-window-handler.md) - tubes WindowHandler slug `sdl3` (live window+renderer).
* [SDL3InputHandler](core/sdl3-input-handler.md) - tubes InputHandler; poll fan-out from WindowHandler. (`draft`)
* [SDL3Renderer2D](core/sdl3-renderer-2d.md) - DrawingAPI on borrowed `Sdl3Framebuffer`.

# Traps

* [Do not resolve gfx / Rendering](traps/no-gfx-registry.md) - `callAfterResolving('gfx', …)` removed until Fabricate Rendering returns.
* [Window attach vs headless](traps/window-attach-deferred.md) - Headless soft surface vs window `attachedTo` / WindowHandler.

# Conventions

* [Registration key sdl3](conventions/registration-key.md) - Stable factory key `sdl3`.

# Log

* [Directory update log](log.md)
