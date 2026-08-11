<?php

use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer as DeferredFramebufferAbstract;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\RenderType;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\Endianness;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\FramebufferKind;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Contracts\Framebuffers\ManagedFramebuffer as ManagedFramebufferContract;
use ScrapyardIO\Tubes\Framebuffers\FramebufferManager;
use ScrapyardIO\Tubes\Framebuffers\ManagedFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\PendingFramebuffer;

function sdl3RowMajor(): FormatSpec
{
    return new FormatSpec(
        PixelFormat::ROW_MAJOR,
        BitDepth::B32,
        endianness: Endianness::MSB,
    );
}

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

test('sized builds a deferred headless SDL soft surface', function () {
    $buffer = Sdl3Framebuffer::sized(8, 8, sdl3RowMajor());

    expect($buffer)->toBeInstanceOf(Sdl3Framebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebufferAbstract::class)
        ->and($buffer)->not->toBeInstanceOf(ManagedFramebuffer::class)
        ->and($buffer)->not->toBeInstanceOf(ManagedFramebufferContract::class)
        ->and($buffer->isHeadless())->toBeTrue()
        ->and($buffer->viewportWidth())->toBe(8)
        ->and($buffer->viewportHeight())->toBe(8)
        ->and($buffer->sdlSurface())->not->toBeNull();
});

test('extendDeferred sdl3 creates via FramebufferManager driver', function () {
    $manager = new FramebufferManager;

    $manager->extendDeferred(
        'sdl3',
        fn (PendingFramebuffer $pending) => Sdl3Framebuffer::sized(
            $pending->widthValue(),
            $pending->heightValue(),
            $pending->hostFormatValue(),
        ),
    );

    $buffer = $manager->driver('sdl3')
        ->size(8, 8)
        ->format(sdl3RowMajor())
        ->create();

    expect($buffer)->toBeInstanceOf(Sdl3Framebuffer::class)
        ->and($buffer)->toBeInstanceOf(DeferredFramebuffer::class)
        ->and($manager->kindOf('sdl3'))->toBe(FramebufferKind::DEFERRED);
});

test('attachedTo borrows a window renderer without owning a soft surface', function () {
    $host = Sdl3Framebuffer::sized(4, 4, sdl3RowMajor());
    $attached = Sdl3Framebuffer::attachedTo($host->sdlRenderer(), sdl3RowMajor(), 4, 4);

    expect($attached->isHeadless())->toBeFalse()
        ->and($attached->sdlSurface())->toBeNull()
        ->and($attached)->toBeInstanceOf(DeferredFramebuffer::class);
});

test('setPixel and flush round-trip on headless SDL store', function () {
    $buffer = Sdl3Framebuffer::sized(4, 2, sdl3RowMajor());
    $buffer->setPixel(1, 0, 0xFF0000FF);

    expect($buffer->getPixel(1, 0))->toBe(0xFF0000FF);

    $frames = $buffer->flush(sdl3RowMajor(), as_array: true);

    expect($frames)->toHaveCount(1)
        ->and($frames[0]->render_type)->toBe(RenderType::PARTIAL)
        ->and($frames[0]->origin_x)->toBe(1)
        ->and($frames[0]->origin_y)->toBe(0)
        ->and(strlen($frames[0]->raw_data))->toBe(4)
        ->and(bin2hex($frames[0]->raw_data))->toBe('ff0000ff');
});

test('markAllDirty flush emits FULL surface bytes', function () {
    $buffer = Sdl3Framebuffer::sized(4, 2, sdl3RowMajor());
    $buffer->fill(0x112233FF);
    $bytes = $buffer->flush(sdl3RowMajor());

    expect($bytes)->toBeString()
        ->and(strlen($bytes))->toBe(4 * 2 * 4);
});

test('flush to RGB565 packs RGBA words correctly and emits PARTIAL for local dirty', function () {
    $buffer = Sdl3Framebuffer::sized(8, 8, sdl3RowMajor());
    $buffer->fill(0x000000FF);
    $buffer->flush(sdl3RowMajor(), as_array: true); // clear prime dirty

    $rgb565 = new FormatSpec(
        PixelFormat::ROW_MAJOR,
        BitDepth::B16,
        endianness: Endianness::MSB,
    );

    $buffer->setSegment(2, 3, 3, 2, 0xFF0000FF); // pure red → 0xF800
    $frames = $buffer->flush($rgb565, as_array: true);

    expect($frames)->toHaveCount(1)
        ->and($frames[0]->render_type)->toBe(RenderType::PARTIAL)
        ->and($frames[0]->origin_x)->toBe(2)
        ->and($frames[0]->origin_y)->toBe(3)
        ->and($frames[0]->width)->toBe(3)
        ->and($frames[0]->height)->toBe(2)
        ->and(bin2hex($frames[0]->raw_data))->toBe(str_repeat('f800', 6));
});

test('headless damageGranularity is pixel-perfect for PanelIC partial', function () {
    $buffer = Sdl3Framebuffer::sized(16, 16, sdl3RowMajor());

    expect($buffer->damageGranularity()->coversWholeSurface())->toBeFalse()
        ->and($buffer->damageGranularity()->isPixelPerfect())->toBeTrue();
});
