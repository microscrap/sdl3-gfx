<?php

use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use Microscrap\GFX\SDL3\SDL3WindowHandler;
use ScrapyardIO\Tubes\Canvas\OSWindow;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Windows\WindowHandler;
use ScrapyardIO\Tubes\Windows\WindowManager;

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

test('SDL3WindowHandler defines rgba FormatSpec at construct', function () {
    $handler = new SDL3WindowHandler('demo', 320, 240);

    expect($handler)->toBeInstanceOf(WindowHandler::class)
        ->and($handler->title())->toBe('demo')
        ->and($handler->width())->toBe(320)
        ->and($handler->height())->toBe(240)
        ->and($handler->isOpen())->toBeFalse()
        ->and($handler->formatSpec()->bit_depth)->toBe(BitDepth::B32)
        ->and($handler->formatSpec()->pixel_format)->toBe(PixelFormat::ROW_MAJOR)
        ->and($handler->formatSpec())->toEqual(Sdl3Framebuffer::rgbaSpec());
});

test('SDL3WindowHandler open present poll close updates a visible window path', function () {
    $handler = new SDL3WindowHandler('sdl3-gfx-test', 64, 48);
    $handler->open();

    expect($handler->isOpen())->toBeTrue()
        ->and($handler->sdlWindow())->not->toBeNull()
        ->and($handler->sdlRenderer())->not->toBeNull();

    $fb = $handler->framebuffer();
    expect($fb)->toBeInstanceOf(Sdl3Framebuffer::class)
        ->and($fb->isHeadless())->toBeFalse();

    $fb->fill(0xFF203040)->setPixel(10, 10, 0xFFFFFFFF);
    $handler->present()->pollEvents();

    expect($handler->shouldClose())->toBeFalse();

    $handler->close();
    expect($handler->isOpen())->toBeFalse()
        ->and($handler->sdlWindow())->toBeNull();
});

test('OSWindow wraps SDL3WindowHandler and open works', function () {
    $window = new OSWindow(new SDL3WindowHandler('canvas', 80, 60));
    $window->open();

    expect($window->title())->toBe('canvas')
        ->and($window->width())->toBe(80)
        ->and($window->height())->toBe(60)
        ->and($window->framebuffer())->toBeInstanceOf(Sdl3Framebuffer::class)
        ->and($window->framebuffer()->isHeadless())->toBeFalse();

    $window->framebuffer()->fill(0x112233FF);
    $window->present()->pollEvents();
    $window->close();
});

test('WindowManager extend sdl3 creates and opens OSWindow', function () {
    $manager = new WindowManager;
    $manager->extend('sdl3', SDL3WindowHandler::class);

    $window = $manager->driver('sdl3')
        ->title('mgr')
        ->size(96, 72)
        ->open();

    expect($window)->toBeInstanceOf(OSWindow::class)
        ->and($window->isOpen())->toBeTrue()
        ->and($window->framebuffer()->isHeadless())->toBeFalse();

    $window->close();
});
