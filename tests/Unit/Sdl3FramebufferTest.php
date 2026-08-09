<?php

use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer as DeferredFramebufferAbstract;
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

    $bytes = $buffer->flush(sdl3RowMajor());

    expect($bytes)->toBeString()
        ->and(strlen($bytes))->toBe(4 * 2 * 4);
});
