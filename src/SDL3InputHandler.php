<?php

namespace Microscrap\GFX\SDL3;

use Microscrap\Bindings\SDL3\DataObjects\SDLEventRef;
use Microscrap\Bindings\SDL3\DataObjects\SDLGamepad;
use Microscrap\Bindings\SDL3\DataObjects\SDLJoystick;
use Microscrap\Bindings\SDL3\Enums\EventType;
use Microscrap\Bindings\SDL3\Enums\GamepadAxis;
use Microscrap\Bindings\SDL3\Enums\GamepadButton;
use Microscrap\Bindings\SDL3\Enums\InitFlag;
use Microscrap\Bindings\SDL3\Enums\JoystickHat;
use Microscrap\Bindings\SDL3\Enums\MouseButtonFlag;
use Microscrap\Bindings\SDL3\Enums\MouseWheelDirection;
use Microscrap\Bindings\SDL3\Enums\Scancode;
use Microscrap\Bindings\SDL3\Gamepad as Sdl3GamepadApi;
use Microscrap\Bindings\SDL3\Init;
use Microscrap\Bindings\SDL3\Joystick as Sdl3JoystickApi;
use Microscrap\Bindings\SDL3\Keyboard as Sdl3KeyboardApi;
use Microscrap\Bindings\SDL3\Mouse as Sdl3MouseApi;
use ScrapyardIO\Tubes\HumanInput\AnalogButton;
use ScrapyardIO\Tubes\HumanInput\AnalogStick;
use ScrapyardIO\Tubes\HumanInput\DigitalButton;
use ScrapyardIO\Tubes\HumanInput\Enums\MouseButton;
use ScrapyardIO\Tubes\HumanInput\GameController;
use ScrapyardIO\Tubes\HumanInput\GamePad;
use ScrapyardIO\Tubes\HumanInput\Keyboard;
use ScrapyardIO\Tubes\HumanInput\Mouse;
use ScrapyardIO\Tubes\Inputs\InputHandler;

/**
 * SDL3 human-input companion — snapshots keyboard/mouse and maps pads/controllers.
 *
 * Wired from {@see SDL3WindowHandler::pollNative()} so one event pump updates
 * window close state and HumanInput devices.
 */
class SDL3InputHandler extends InputHandler
{
    protected static bool $gamepad_initialized = false;

    /** @var array<int, SDLGamepad> */
    protected array $open_gamepads = [];

    /** @var array<int, SDLJoystick> */
    protected array $open_joysticks = [];

    protected float $pending_wheel_delta = 0.0;

    /**
     * Fan-out hook while draining the SDL event queue.
     *
     * Returns true when this call already freed the native event (mouse wheel
     * readers in ext-sdl3 free the SDL_Event).
     */
    public function ingestEvent(SDLEventRef $event): bool
    {
        if ($event->eventType === EventType::SDL_EVENT_MOUSE_WHEEL->value) {
            $wheel = Sdl3MouseApi::readMouseWheelEvent($event);
            $delta = (float) ($wheel['y'] ?? 0.0);
            $direction = (int) ($wheel['direction'] ?? MouseWheelDirection::SDL_MOUSEWHEEL_NORMAL->value);
            if ($direction === MouseWheelDirection::SDL_MOUSEWHEEL_FLIPPED->value) {
                $delta = -$delta;
            }
            $this->pending_wheel_delta += $delta;

            return true;
        }

        return false;
    }

    public function poll(): static
    {
        $this->ensureGamepadSubsystem();
        $this->refreshKeyboard();
        $this->refreshMouse();
        $this->refreshGameControllers();
        $this->refreshGamePads();

        return $this;
    }

    public function closeNative(): void
    {
        foreach ($this->open_gamepads as $gamepad) {
            Sdl3GamepadApi::closeGamepad($gamepad);
        }
        $this->open_gamepads = [];

        foreach ($this->open_joysticks as $joystick) {
            Sdl3JoystickApi::closeJoystick($joystick);
        }
        $this->open_joysticks = [];

        $this->keyboard = null;
        $this->mouse = null;
        $this->game_pads = [];
        $this->game_controllers = [];
        $this->pending_wheel_delta = 0.0;
    }

    protected function ensureGamepadSubsystem(): void
    {
        if (static::$gamepad_initialized) {
            return;
        }

        if (Init::wasInit(InitFlag::SDL_INIT_GAMEPAD) === 0) {
            Init::initSubSystem(InitFlag::SDL_INIT_GAMEPAD);
        }

        static::$gamepad_initialized = true;
    }

    protected function refreshKeyboard(): void
    {
        $state = Sdl3KeyboardApi::getKeyboardState();
        $keys = [];

        foreach ($state as $scancode => $pressed) {
            if ($pressed !== true) {
                continue;
            }

            $scancode = (int) $scancode;
            if ($scancode === Scancode::SDL_SCANCODE_UNKNOWN->value) {
                continue;
            }

            $name = strtolower(trim(Sdl3KeyboardApi::getScancodeName($scancode)));
            if ($name === '' || $name === 'unknown') {
                continue;
            }

            $keys[$name] = true;
        }

        $this->keyboard = new Keyboard($keys);
    }

    protected function refreshMouse(): void
    {
        $state = Sdl3MouseApi::getMouseState();
        $buttons_mask = (int) ($state['buttons'] ?? 0);
        $wheel = $this->pending_wheel_delta;
        $this->pending_wheel_delta = 0.0;

        $this->mouse = new Mouse(
            (float) ($state['x'] ?? 0.0),
            (float) ($state['y'] ?? 0.0),
            [
                new DigitalButton(MouseButton::LEFT->value, ($buttons_mask & MouseButtonFlag::SDL_BUTTON_LMASK->value) !== 0),
                new DigitalButton(MouseButton::MIDDLE->value, ($buttons_mask & MouseButtonFlag::SDL_BUTTON_MMASK->value) !== 0),
                new DigitalButton(MouseButton::RIGHT->value, ($buttons_mask & MouseButtonFlag::SDL_BUTTON_RMASK->value) !== 0),
                new DigitalButton(MouseButton::X1->value, ($buttons_mask & MouseButtonFlag::SDL_BUTTON_X1MASK->value) !== 0),
                new DigitalButton(MouseButton::X2->value, ($buttons_mask & MouseButtonFlag::SDL_BUTTON_X2MASK->value) !== 0),
            ],
            $wheel,
        );
    }

    protected function refreshGameControllers(): void
    {
        Sdl3GamepadApi::updateGamepads();

        $ids = Sdl3GamepadApi::getGamepads();
        $active = [];
        $controllers = [];

        foreach ($ids as $instance_id) {
            $instance_id = (int) $instance_id;
            $gamepad = $this->open_gamepads[$instance_id] ?? Sdl3GamepadApi::openGamepad($instance_id);
            if (is_null($gamepad)) {
                continue;
            }

            $this->open_gamepads[$instance_id] = $gamepad;
            $active[$instance_id] = true;

            $controller = $this->mapGamepadToController($gamepad);
            if (! is_null($controller)) {
                $controllers[] = $controller;
            }
        }

        foreach (array_keys($this->open_gamepads) as $instance_id) {
            if (! isset($active[$instance_id])) {
                Sdl3GamepadApi::closeGamepad($this->open_gamepads[$instance_id]);
                unset($this->open_gamepads[$instance_id]);
            }
        }

        $this->game_controllers = $controllers;
    }

    protected function refreshGamePads(): void
    {
        Sdl3JoystickApi::updateJoysticks();

        $ids = Sdl3JoystickApi::getJoysticks();
        $active = [];
        $pads = [];

        foreach ($ids as $instance_id) {
            $instance_id = (int) $instance_id;
            if (Sdl3GamepadApi::isGamepad($instance_id)) {
                continue;
            }

            $joystick = $this->open_joysticks[$instance_id] ?? Sdl3JoystickApi::openJoystick($instance_id);
            if (is_null($joystick)) {
                continue;
            }

            $this->open_joysticks[$instance_id] = $joystick;
            $active[$instance_id] = true;
            $pads[] = $this->mapJoystickToGamePad($joystick);
        }

        foreach (array_keys($this->open_joysticks) as $instance_id) {
            if (! isset($active[$instance_id])) {
                Sdl3JoystickApi::closeJoystick($this->open_joysticks[$instance_id]);
                unset($this->open_joysticks[$instance_id]);
            }
        }

        $this->game_pads = $pads;
    }

    protected function mapGamepadToController(SDLGamepad $gamepad): ?GameController
    {
        $controls = [];

        foreach (GamepadButton::cases() as $button) {
            if (
                $button === GamepadButton::SDL_GAMEPAD_BUTTON_INVALID
                || $button === GamepadButton::SDL_GAMEPAD_BUTTON_COUNT
            ) {
                continue;
            }

            if (! Sdl3GamepadApi::gamepadHasButton($gamepad, $button)) {
                continue;
            }

            $name = Sdl3GamepadApi::getGamepadStringForButton($button);
            if ($name === '') {
                $name = strtolower($button->name);
            }

            $controls[] = new DigitalButton($name, Sdl3GamepadApi::getGamepadButton($gamepad, $button));
        }

        foreach ([GamepadAxis::SDL_GAMEPAD_AXIS_LEFT_TRIGGER, GamepadAxis::SDL_GAMEPAD_AXIS_RIGHT_TRIGGER] as $axis) {
            if (! Sdl3GamepadApi::gamepadHasAxis($gamepad, $axis)) {
                continue;
            }

            $name = Sdl3GamepadApi::getGamepadStringForAxis($axis);
            if ($name === '') {
                $name = strtolower($axis->name);
            }

            $controls[] = new AnalogButton($name, $this->normalizeTrigger(Sdl3GamepadApi::getGamepadAxis($gamepad, $axis)));
        }

        $left = $this->stickFromAxes(
            $gamepad,
            'left',
            GamepadAxis::SDL_GAMEPAD_AXIS_LEFTX,
            GamepadAxis::SDL_GAMEPAD_AXIS_LEFTY,
        );
        if (! is_null($left)) {
            $controls[] = $left;
        }

        $right = $this->stickFromAxes(
            $gamepad,
            'right',
            GamepadAxis::SDL_GAMEPAD_AXIS_RIGHTX,
            GamepadAxis::SDL_GAMEPAD_AXIS_RIGHTY,
        );
        if (! is_null($right)) {
            $controls[] = $right;
        }

        $has_stick = ! is_null($left) || ! is_null($right);
        $has_button = false;
        foreach ($controls as $control) {
            if ($control instanceof DigitalButton || $control instanceof AnalogButton) {
                $has_button = true;
                break;
            }
        }

        if (! $has_stick || ! $has_button) {
            return null;
        }

        $name = Sdl3GamepadApi::getGamepadName($gamepad);
        if ($name === '') {
            $name = 'gamepad-'.Sdl3GamepadApi::getGamepadID($gamepad);
        }

        return new GameController($name, $controls);
    }

    protected function mapJoystickToGamePad(SDLJoystick $joystick): GamePad
    {
        $controls = [];
        $button_count = Sdl3JoystickApi::getNumJoystickButtons($joystick);
        for ($i = 0; $i < $button_count; $i++) {
            $controls[] = new DigitalButton('button_'.$i, Sdl3JoystickApi::getJoystickButton($joystick, $i));
        }

        $hat_count = Sdl3JoystickApi::getNumJoystickHats($joystick);
        for ($hat = 0; $hat < $hat_count; $hat++) {
            $value = Sdl3JoystickApi::getJoystickHat($joystick, $hat);
            $controls[] = new DigitalButton('hat'.$hat.'_up', ($value & JoystickHat::SDL_HAT_UP->value) !== 0);
            $controls[] = new DigitalButton('hat'.$hat.'_right', ($value & JoystickHat::SDL_HAT_RIGHT->value) !== 0);
            $controls[] = new DigitalButton('hat'.$hat.'_down', ($value & JoystickHat::SDL_HAT_DOWN->value) !== 0);
            $controls[] = new DigitalButton('hat'.$hat.'_left', ($value & JoystickHat::SDL_HAT_LEFT->value) !== 0);
        }

        $name = Sdl3JoystickApi::getJoystickName($joystick);
        if ($name === '') {
            $name = 'joystick-'.Sdl3JoystickApi::getJoystickID($joystick);
        }

        return new GamePad($name, $controls);
    }

    protected function stickFromAxes(
        SDLGamepad $gamepad,
        string $name,
        GamepadAxis $axis_x,
        GamepadAxis $axis_y,
    ): ?AnalogStick {
        $has_x = Sdl3GamepadApi::gamepadHasAxis($gamepad, $axis_x);
        $has_y = Sdl3GamepadApi::gamepadHasAxis($gamepad, $axis_y);
        if (! $has_x && ! $has_y) {
            return null;
        }

        $x = $has_x ? $this->normalizeAxis(Sdl3GamepadApi::getGamepadAxis($gamepad, $axis_x)) : 0.0;
        $y = $has_y ? $this->normalizeAxis(Sdl3GamepadApi::getGamepadAxis($gamepad, $axis_y)) : 0.0;

        return new AnalogStick($name, $x, $y);
    }

    protected function normalizeAxis(int $raw): float
    {
        if ($raw >= 0) {
            return min(1.0, $raw / 32767.0);
        }

        return max(-1.0, $raw / 32768.0);
    }

    protected function normalizeTrigger(int $raw): float
    {
        return max(0.0, min(1.0, $raw / 32767.0));
    }
}
