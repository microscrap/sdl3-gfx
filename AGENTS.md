# Agent guidelines — microscrap/sdl3-gfx

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from Composer dist via `.gitattributes` `export-ignore`).

Before changing GFX/framebuffer code **for this package**:

1. Read [`.okf/index.md`](.okf/index.md) first (progressive disclosure).
2. Open only the linked concepts needed for the task.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only. New/changed concepts stay `status: draft` until a human verifies them.
4. When you learn something durable about **this package**, update the affected `.okf` concept(s) and append `.okf/log.md`.
5. Keep the `.okf` bundle at the **package root** only — do not nest extra `.okf` folders under `src/`.
6. Bindings knowledge → `microscrap/sdl3`. Tubes factory/PixelStore → `scrapyard-io/tubes`. Extension build → `php-io-extensions/sdl3`.

## Package rules (quick) — 0.7.x

- Composer: `microscrap/sdl3-gfx` **0.7.0**. PHP `^8.4|^8.5|^8.6`.
- Depends on `microscrap/sdl3`, `tubes/framebuffers`, `tubes/contracts`, `fabricate/nuts-and-bolts` (`^0.7.0`).
- Provider registers **`extendDeferred('sdl3', …)`** + **`WindowFactory::extend('sdl3', …)`** — **not** `extendManaged`. Soft Managed = tubes `full`/`dirty`/`page` only.
- `Sdl3Framebuffer` implements **`DeferredFramebuffer`**. SDL owns pixels (microscrap/sdl3 Surface/Render API).
- **Headless default** = off-screen SDL soft surface + software renderer (no window). **Windowed** = `SDL3WindowHandler` → `attachedTo($renderer, …)`.
- `SDL3Renderer2D` is the tubes DrawingAPI companion (parity with metal/ogx/vulkan/cuda).
- Human Input: `SDL3InputHandler` extends tubes `InputHandler`; fan-out lives in `SDL3WindowHandler::pollNative` (one pump for close + devices). Wrap with `EngineInput`. See `.okf/core/sdl3-input-handler.md`.
- Never model `sdl3` as a PHP `PixelStore` / Managed concrete.
- `SDL3Gfx` / `SDL3GFXRenderDriver` / `InstallSdl3DisplayCommand` may remain in tree but stay **unregistered** until Fabricate Rendering / Console paths return.
- Registration key stays **`sdl3`**. Namespace `Microscrap\GFX\SDL3\`.
