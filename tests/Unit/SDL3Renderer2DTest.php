<?php

use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use Microscrap\GFX\SDL3\SDL3Renderer2D;
use Microscrap\GFX\SDL3\SDL3WindowHandler;
use ScrapyardIO\Tubes\Contracts\Rendering\DrawingAPI;
use ScrapyardIO\Tubes\Contracts\Rendering\RenderingException;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

function sdl3RendererBound(int $w = 64, int $h = 48): array
{
    $fb = Sdl3Framebuffer::sized($w, $h, Sdl3Framebuffer::rgbaSpec());
    $renderer = new SDL3Renderer2D;
    $renderer->setFramebuffer($fb);

    return [$renderer, $fb];
}

test('SDL3Renderer2D implements DrawingAPI and Renderer2D', function () {
    $renderer = new SDL3Renderer2D;

    expect($renderer)->toBeInstanceOf(Renderer2D::class)
        ->and($renderer)->toBeInstanceOf(DrawingAPI::class);
});

test('SDL3Renderer2D throws when drawing unbound', function () {
    $renderer = new SDL3Renderer2D;

    expect(fn () => $renderer->drawPixel(0, 0, 0xFFFFFFFF))
        ->toThrow(RenderingException::class);
});

test('SDL3Renderer2D fill uses SDL renderClear', function () {
    [$renderer, $fb] = sdl3RendererBound(16, 12);

    $renderer->fill(0x112233FF);

    expect($fb->getPixel(0, 0))->toBe(0x112233FF)
        ->and($fb->getPixel(15, 11))->toBe(0x112233FF);
});

test('SDL3Renderer2D drawPixel and drawLine write into the borrowed SDL buffer', function () {
    [$renderer, $fb] = sdl3RendererBound();

    $renderer->fill(0x000000FF)
        ->drawPixel(5, 5, 0xFF0000FF)
        ->drawLine(0, 0, 10, 0, 0x00FF00FF);

    expect($fb->getPixel(5, 5))->toBe(0xFF0000FF)
        ->and($fb->getPixel(0, 0))->toBe(0x00FF00FF)
        ->and($fb->getPixel(10, 0))->toBe(0x00FF00FF);
});

test('SDL3Renderer2D rect circle triangle primitives paint expected pixels', function () {
    [$renderer, $fb] = sdl3RendererBound(80, 60);

    $renderer->fill(0x000000FF)
        ->drawRect(2, 2, 10, 8, 0xAAAAAAFF)
        ->fillRect(20, 4, 6, 4, 0x00FFFFFF)
        ->drawCircle(40, 30, 8, 0xFF00FFFF)
        ->fillCircle(60, 30, 5, 0xFFFF00FF)
        ->fillTriangle(50, 45, 70, 45, 60, 55, 0x888888FF);

    expect($fb->getPixel(2, 2))->toBe(0xAAAAAAFF)
        ->and($fb->getPixel(22, 5))->toBe(0x00FFFFFF)
        ->and($fb->getPixel(40, 22))->toBe(0xFF00FFFF)
        ->and($fb->getPixel(60, 30))->toBe(0xFFFF00FF)
        ->and($fb->getPixel(60, 50))->toBe(0x888888FF);
});

test('SDL3Renderer2D works with a window-bound SDL framebuffer', function () {
    $handler = new SDL3WindowHandler('renderer', 96, 72);
    $handler->open();
    $fb = $handler->framebuffer();

    $renderer = new SDL3Renderer2D;
    $renderer->setFramebuffer($fb);
    $renderer->fill(0x203040FF)
        ->fillCircle(48, 36, 12, 0xE0E0E0FF)
        ->drawRect(8, 8, 80, 56, 0xFFFFFFFF);

    $handler->present()->pollEvents();

    expect($fb->isHeadless())->toBeFalse();

    $handler->close();
});
