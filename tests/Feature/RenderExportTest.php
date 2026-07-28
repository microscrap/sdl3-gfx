<?php

namespace DeptOfScrapyardRobotics\Tests\Feature;

use Fabricate\Contracts\Framebuffers\Enums\BitDepth;
use Fabricate\Contracts\Framebuffers\Enums\BitOrder;
use Fabricate\Contracts\Framebuffers\Enums\Endianness;
use Fabricate\Contracts\Framebuffers\Enums\PageAxis;
use Fabricate\Contracts\Framebuffers\Enums\PixelFormat;
use Fabricate\Contracts\Framebuffers\Enums\RenderType;
use Fabricate\Contracts\Framebuffers\Enums\ScanDirection;
use Fabricate\Framebuffers\FormatSpec;
use Microscrap\Bindings\SDL3\Enums\PixelFormat as SdlPixelFormat;
use Microscrap\Bindings\SDL3\Render;
use Microscrap\Bindings\SDL3\Surface;
use Microscrap\GFX\SDL3\SDL3Gfx;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;

require_once dirname(__DIR__).'/Helpers.php';

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

it('exports a FULL ROW_MAJOR B32 frame with RGBA bytes', function (): void {
    $gfx = sdlGfx(4, 2)->drawPixel(1, 0, 0xFF0000FF);

    $frames = $gfx->render();

    expect($frames)->toHaveCount(1);

    $frame = $frames[0];

    expect($frame->render_type)->toBe(RenderType::FULL)
        ->and($frame->metadata->pixel_format)->toBe(PixelFormat::ROW_MAJOR)
        ->and($frame->metadata->bit_depth)->toBe(BitDepth::B32)
        ->and($frame->metadata->endianness)->toBe(Endianness::MSB)
        ->and($frame->width)->toBe(4)
        ->and($frame->height)->toBe(2)
        ->and($frame->raw_data)->toHaveCount(4 * 2 * 4);

    // Pixel (1,0) sits at byte offset 4: R,G,B,A
    expect(array_slice($frame->raw_data, 4, 4))->toBe([255, 0, 0, 255])
        ->and(array_slice($frame->raw_data, 0, 4))->toBe([0, 0, 0, 0]);
});

it('renders the same frame bytes across repeated exports', function (): void {
    $gfx = sdlGfx(8, 8)->fillRect(2, 2, 4, 4, 0x00FF00FF);

    expect($gfx->render()[0]->raw_data)->toBe($gfx->render()[0]->raw_data);
});

it('packs an SDL surface back into its embedded working format', function (): void {
    $spec = new FormatSpec(
        PixelFormat::MONO_VERTICAL_PAGE,
        BitDepth::B1,
        ScanDirection::TOP_TO_BOTTOM,
        BitOrder::LSB_FIRST,
        page_axis: PageAxis::VERTICAL,
    );
    $buffer = new Sdl3Framebuffer($spec, 8, 8);
    $buffer->setPixel(0, 0, 1);

    $frame = $buffer->dump()[0];

    expect($frame->metadata)->toBe($spec)
        ->and($frame->raw_data)->toBe([1, 0, 0, 0, 0, 0, 0, 0]);
});

it('attaches to an externally owned SDL renderer (windowed mode)', function (): void {
    $surface = Surface::createSurface(6, 6, SdlPixelFormat::SDL_PIXELFORMAT_RGBA8888);
    $renderer = Render::createSoftwareRenderer($surface->ptr);

    try {
        $gfx = SDL3Gfx::windowed($renderer, 6, 6, sdlRgbaSpec());

        expect($gfx->buffer()->isHeadless())->toBeFalse();

        $gfx->fill(0x000000FF)->drawPixel(5, 5, 0x0000FFFF);

        $frame = $gfx->render()[0];
        $offset = ((5 * 6) + 5) * 4;

        expect(array_slice($frame->raw_data, $offset, 4))->toBe([0, 0, 255, 255]);
    } finally {
        Render::destroyRenderer($renderer);
        Surface::destroySurface($surface);
    }
});

