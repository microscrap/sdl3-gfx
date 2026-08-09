# Directory Update Log

## 2026-08-09

* **HumanInput (draft)**: [SDL3InputHandler](core/sdl3-input-handler.md) — tubes `InputHandler` for SDL3; `SDL3WindowHandler::pollNative` fans out (`ingestEvent` + snapshot `poll`) before freeing events; Gamepad→`GameController`, non-gamepad Joystick→`GamePad`. Pest `SDL3InputHandlerTest`.
* **Parity (draft)**: [SDL3WindowHandler](core/sdl3-window-handler.md) live — `Init(VIDEO)` + `createWindowAndRenderer` + `attachedTo` + `RenderPresent` + event poll; [SDL3Renderer2D](core/sdl3-renderer-2d.md) DrawingAPI + `DrawsText`. Pest window/renderer suites; ecosystem overview/usage at metal/ogx parity. Flush event queue on boot so short-lived Pest windows do not inherit prior `QUIT`.
* **Verify (superseded)**: Earlier same-day note that WindowHandler native boot remained stubbed — replaced by Parity entry above.

## 2026-08-08

* **Initialization (draft)**: Created OKF v0.2 for `microscrap/sdl3-gfx` documenting the **0.6-shaped** surface (Fabricate Framebuffers `extend`, GFX registry, SDL-owned soft surface).
* **Amend (draft)**: Incorrectly documented Managed/`extendManaged` PixelStore path — **reverted**.
* **Amend (draft)**: Align to tubes abstract — `Sdl3Framebuffer extends DeferredFramebuffer`; `fill(int)` replaces engine `clear(int)`; publish stub `config/framebuffers/sdl3.php` tag `tubes-framebuffers-sdl3`.
* **Amend (draft)**: **Deferred** is correct — `extendDeferred('sdl3')`, `Sdl3Framebuffer implements DeferredFramebuffer`, default headless = SDL soft surface + software renderer via microscrap/sdl3; `attachedTo()` for windowed. Soft `full`/`dirty`/`page` remain Managed in tubes.
