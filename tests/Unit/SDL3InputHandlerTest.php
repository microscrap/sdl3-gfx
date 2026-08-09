<?php

use Microscrap\Bindings\SDL3\Enums\GamepadAxis;
use Microscrap\Bindings\SDL3\Enums\GamepadButton;
use Microscrap\Bindings\SDL3\Enums\InitFlag;
use Microscrap\Bindings\SDL3\Enums\JoystickType;
use Microscrap\Bindings\SDL3\Gamepad as Sdl3GamepadApi;
use Microscrap\Bindings\SDL3\Init;
use Microscrap\Bindings\SDL3\Joystick;
use Microscrap\GFX\SDL3\SDL3InputHandler;
use Microscrap\GFX\SDL3\SDL3WindowHandler;
use ScrapyardIO\Tubes\HumanInput\EngineInput;
use ScrapyardIO\Tubes\HumanInput\Enums\MouseButton;
use ScrapyardIO\Tubes\HumanInput\GameController;
use ScrapyardIO\Tubes\HumanInput\GamePad;
use ScrapyardIO\Tubes\Inputs\InputHandler;

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

test('SDL3InputHandler extends tubes InputHandler', function () {
    $handler = new SDL3InputHandler;

    expect($handler)->toBeInstanceOf(InputHandler::class)
        ->and($handler->keyboard())->toBeNull()
        ->and($handler->mouse())->toBeNull()
        ->and($handler->gamePads())->toBe([])
        ->and($handler->gameControllers())->toBe([]);
});

test('SDL3WindowHandler pollEvents fans out keyboard and mouse snapshots', function () {
    $window = new SDL3WindowHandler('sdl3-input', 64, 48);
    $window->open();

    $handler = $window->inputHandler();
    expect($handler)->toBeInstanceOf(SDL3InputHandler::class);

    $window->pollEvents();

    expect($handler->keyboard())->not->toBeNull()
        ->and($handler->mouse())->not->toBeNull()
        ->and($handler->mouse()->button(MouseButton::LEFT))->not->toBeNull()
        ->and($handler->mouse()->wheelDelta())->toBe(0.0);

    $engine = new EngineInput($handler);
    $engine->poll();

    expect($engine->keyboard())->toBe($handler->keyboard())
        ->and($engine->mouse())->toBe($handler->mouse());

    $window->close();
});

test('SDL3InputHandler maps a virtual SDL gamepad to GameController', function () {
    expect(Init::init(0))->toBeTrue();
    expect(Init::initSubSystem(InitFlag::SDL_INIT_GAMEPAD))->toBeTrue();

    $instance_id = Joystick::attachVirtualJoystick([
        'type' => JoystickType::SDL_JOYSTICK_TYPE_GAMEPAD->value,
        'naxes' => 6,
        'nbuttons' => 12,
        'ntouchpads' => 0,
        'nsensors' => 0,
        'name' => 'sdl3-gfx Virtual Controller',
    ]);
    expect($instance_id)->toBeGreaterThan(0);

    $guid = Joystick::getJoystickGUIDForID($instance_id);
    $mapping = sprintf(
        '%s,sdl3-gfx Virtual Controller,a:b0,b:b1,x:b2,y:b3,back:b4,start:b5,'
        .'leftshoulder:b6,rightshoulder:b7,leftx:a0,lefty:a1,rightx:a2,righty:a3,'
        .'lefttrigger:a4,righttrigger:a5,dpup:b8,dpdown:b9,dpleft:b10,dpright:b11',
        $guid
    );

    try {
        expect(Sdl3GamepadApi::addGamepadMapping($mapping))->toBeGreaterThanOrEqual(0);
        expect(Sdl3GamepadApi::isGamepad($instance_id))->toBeTrue();

        $pad = Sdl3GamepadApi::openGamepad($instance_id);
        expect($pad)->not->toBeNull();

        try {
            $joystick = Sdl3GamepadApi::getGamepadJoystick($pad);
            expect($joystick)->not->toBeNull();
            expect(Joystick::setJoystickVirtualAxis($joystick, 0, 32767))->toBeTrue();
            expect(Joystick::setJoystickVirtualButton($joystick, 0, true))->toBeTrue();
            Joystick::updateJoysticks();
            Sdl3GamepadApi::updateGamepads();

            expect(Sdl3GamepadApi::getGamepadAxis($pad, GamepadAxis::SDL_GAMEPAD_AXIS_LEFTX))->toBe(32767);
            expect(Sdl3GamepadApi::getGamepadButton($pad, GamepadButton::SDL_GAMEPAD_BUTTON_SOUTH))->toBeTrue();

            $handler = new SDL3InputHandler;
            $handler->poll();

            expect($handler->gameControllers())->not->toBeEmpty();
            $controller = $handler->gameControllers()[0];
            expect($controller)->toBeInstanceOf(GameController::class)
                ->and($controller->sticks())->not->toBeEmpty()
                ->and($controller->digitalButtons())->not->toBeEmpty();

            $left = null;
            foreach ($controller->sticks() as $stick) {
                if ($stick->name() === 'left') {
                    $left = $stick;
                    break;
                }
            }
            expect($left)->not->toBeNull()
                ->and($left->x())->toBe(1.0);

            $south = null;
            foreach ($controller->digitalButtons() as $button) {
                if ($button->name() === 'a') {
                    $south = $button;
                    break;
                }
            }
            expect($south)->not->toBeNull()
                ->and($south->isPressed())->toBeTrue();

            // Mapped SDL gamepads are GameControllers, not button-only GamePads.
            foreach ($handler->gamePads() as $game_pad) {
                expect($game_pad->name())->not->toBe('sdl3-gfx Virtual Controller');
            }

            $handler->closeNative();
        } finally {
            Sdl3GamepadApi::closeGamepad($pad);
        }
    } finally {
        Joystick::detachVirtualJoystick($instance_id);
    }
});

test('SDL3InputHandler maps a non-gamepad virtual joystick to GamePad', function () {
    expect(Init::init(0))->toBeTrue();
    expect(Init::initSubSystem(InitFlag::SDL_INIT_JOYSTICK))->toBeTrue();

    $instance_id = Joystick::attachVirtualJoystick([
        'type' => JoystickType::SDL_JOYSTICK_TYPE_UNKNOWN->value,
        'naxes' => 0,
        'nbuttons' => 4,
        'nhats' => 1,
        'ntouchpads' => 0,
        'nsensors' => 0,
        'name' => 'sdl3-gfx Virtual Pad',
    ]);
    expect($instance_id)->toBeGreaterThan(0);

    try {
        expect(Sdl3GamepadApi::isGamepad($instance_id))->toBeFalse();

        $joystick = Joystick::openJoystick($instance_id);
        expect($joystick)->not->toBeNull();

        try {
            expect(Joystick::setJoystickVirtualButton($joystick, 2, true))->toBeTrue();
            Joystick::updateJoysticks();

            $handler = new SDL3InputHandler;
            $handler->poll();

            expect($handler->gamePads())->not->toBeEmpty();
            $pad = $handler->gamePads()[0];
            expect($pad)->toBeInstanceOf(GamePad::class)
                ->and($pad->name())->toBe('sdl3-gfx Virtual Pad')
                ->and($pad->digitalButtons())->not->toBeEmpty();

            $button2 = null;
            foreach ($pad->digitalButtons() as $button) {
                if ($button->name() === 'button_2') {
                    $button2 = $button;
                    break;
                }
            }
            expect($button2)->not->toBeNull()
                ->and($button2->isPressed())->toBeTrue();

            foreach ($handler->gameControllers() as $controller) {
                expect($controller->name())->not->toBe('sdl3-gfx Virtual Pad');
            }

            $handler->closeNative();
        } finally {
            Joystick::closeJoystick($joystick);
        }
    } finally {
        Joystick::detachVirtualJoystick($instance_id);
    }
});
