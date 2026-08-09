---
type: Core
title: SDL3InputHandler
description: "tubes InputHandler for SDL3 — keyboard/mouse snapshots + Gamepad→GameController / Joystick→GamePad; fan-out from SDL3WindowHandler::pollNative."
resource: src/SDL3InputHandler.php
tags: [core, human-input, input-handler, sdl3]
generated: { by: "cursor-agent/grok-4.5", at: "2026-08-09T07:00:00Z" }
status: draft
---

# Role

`Microscrap\GFX\SDL3\SDL3InputHandler` extends tubes `ScrapyardIO\Tubes\Inputs\InputHandler` for the SDL3 window pump.

# Mapping

| SDL source | tubes device |
|------------|--------------|
| `Keyboard::getKeyboardState` + scancode names | `Keyboard` (pressed keys only) |
| `Mouse::getMouseState` + wheel events | `Mouse` (position, L/M/R/X1/X2, wheel delta) |
| `Gamepad` (mapped) | `GameController` (≥1 stick + ≥1 button) |
| Non-gamepad `Joystick` | `GamePad` (digital buttons + hat bits; no sticks) |

Axes normalize to `-1..1`; triggers to `0..1`.

# Fan-out

[`SDL3WindowHandler::pollNative()`](sdl3-window-handler.md):

1. Drain `Events::pollEvent`.
2. Quit / close-requested → close flag.
3. `ingestEvent()` before free (mouse wheel readers in ext-sdl3 free the native event).
4. After the drain, `inputHandler()->poll()` refreshes device snapshots.

`inputHandler()` is lazy and shared — wrap with `EngineInput` for the HumanInput host API.

# Related

- tubes `.okf/core/input-handler.md`
- tubes `.okf/core/human-input.md`
- [SDL3WindowHandler](sdl3-window-handler.md)
