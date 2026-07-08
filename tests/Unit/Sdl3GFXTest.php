<?php

namespace DeptOfScrapyardRobotics\Tests\Unit;

use BareMetal\Contracts\Framebuffers\DTO\FormatSpec;
use BareMetal\Contracts\Framebuffers\Enums\BitDepth;
use BareMetal\Contracts\Framebuffers\Enums\PixelFormat;
use BareMetal\Framebuffers\FullFramebuffer;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use Microscrap\GFX\SDL3\Sdl3GFX;
use Microscrap\GFX\SDL3\Sdl3GFXException;
use OutOfBoundsException;

beforeEach(function (): void {
    if (! extension_loaded('sdl3')) {
        $this->markTestSkipped('ext-sdl3 is not loaded');
    }
});

describe('construction', function (): void {
    it('builds a headless renderer over an off-screen SDL surface', function (): void {
        $gfx = sdlGfx(16, 8);

        expect($gfx->buffer())->toBeInstanceOf(Sdl3Framebuffer::class)
            ->and($gfx->buffer()->isHeadless())->toBeTrue()
            ->and($gfx->width())->toBe(16)
            ->and($gfx->height())->toBe(8);
    });

    it('adopts a foreign framebuffer by borrowing its spec and dimensions', function (): void {
        $foreign = new FullFramebuffer(10, 6, new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16));

        $gfx = new Sdl3GFX($foreign);

        expect($gfx->buffer())->toBeInstanceOf(Sdl3Framebuffer::class)
            ->and($gfx->buffer()->viewportWidth())->toBe(10)
            ->and($gfx->buffer()->viewportHeight())->toBe(6)
            ->and($gfx->buffer()->formatSpec()->bit_depth)->toBe(BitDepth::B16);
    });

    it('returns its own SDL framebuffer as the preferred framebuffer', function (): void {
        $buffer = Sdl3GFX::preferredFramebuffer(sdlRgbaSpec(), 12, 5);

        expect($buffer)->toBeInstanceOf(Sdl3Framebuffer::class)
            ->and($buffer->viewportWidth())->toBe(12)
            ->and($buffer->viewportHeight())->toBe(5);
    });
});

describe('pixels and fills', function (): void {
    it('paints a single pixel at the addressed coordinate', function (): void {
        $gfx = sdlGfx()->drawPixel(3, 2, 0xFF0000FF);

        expect(sdlWord($gfx, 3, 2))->toBe(0xFF0000FF)
            ->and(sdlPaintedCount($gfx))->toBe(1);
    });

    it('silently clips out-of-bounds pixels', function (): void {
        $gfx = sdlGfx()->drawPixel(-1, 0, 0xFF0000FF)->drawPixel(8, 8, 0xFF0000FF);

        expect(sdlPaintedCount($gfx))->toBe(0);
    });

    it('draws a batch of pixels', function (): void {
        $gfx = sdlGfx()->drawPixels([[0, 0, 0xFF0000FF], [7, 7, 0x00FF00FF]]);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFF0000FF)
            ->and(sdlWord($gfx, 7, 7))->toBe(0x00FF00FF);
    });

    it('fills the whole target with fill()', function (): void {
        $gfx = sdlGfx()->fill(0x0000FFFF);

        expect(sdlPaintedCount($gfx))->toBe(64)
            ->and(sdlWord($gfx, 4, 4))->toBe(0x0000FFFF);
    });

    it('fills an exact rectangular region', function (): void {
        $gfx = sdlGfx()->fillRect(2, 1, 3, 4, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(12)
            ->and(sdlWord($gfx, 2, 1))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 4, 4))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 5, 1))->toBe(0)
            ->and(sdlWord($gfx, 2, 5))->toBe(0);
    });

    it('clips fills that extend past the viewport', function (): void {
        $gfx = sdlGfx()->fillRect(6, 6, 5, 5, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(4);
    });

    it('outlines a rectangle without filling it', function (): void {
        $gfx = sdlGfx()->drawRect(1, 1, 5, 4, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(14)
            ->and(sdlWord($gfx, 1, 1))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 5, 4))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 3, 2))->toBe(0);
    });
});

describe('lines', function (): void {
    it('draws horizontal lines natively', function (): void {
        $gfx = sdlGfx()->drawHorizontalLine(1, 3, 5, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(5);
        for ($x = 1; $x <= 5; $x++) {
            expect(sdlWord($gfx, $x, 3))->toBe(0xFFFFFFFF);
        }
    });

    it('draws vertical lines natively', function (): void {
        $gfx = sdlGfx()->drawVerticalLine(2, 1, 4, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(4);
        for ($y = 1; $y <= 4; $y++) {
            expect(sdlWord($gfx, 2, $y))->toBe(0xFFFFFFFF);
        }
    });

    it('normalizes negative spans like the software renderer', function (): void {
        $gfx = sdlGfx()->drawHorizontalLine(5, 2, -3, 0xFFFFFFFF);

        expect(sdlWord($gfx, 3, 2))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 5, 2))->toBe(0xFFFFFFFF)
            ->and(sdlPaintedCount($gfx))->toBe(3);
    });

    it('routes axis-aligned drawLine calls through the segment fast path', function (): void {
        $gfx = sdlGfx()->drawLine(0, 0, 0, 7, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(8);
    });

    it('rasterizes diagonals through the native SDL line', function (): void {
        $gfx = sdlGfx()->drawLine(0, 0, 7, 7, 0xFFFFFFFF);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 7, 7))->toBe(0xFFFFFFFF)
            ->and(sdlPaintedCount($gfx))->toBeGreaterThanOrEqual(8);
    });

    it('drops lines that never touch the viewport', function (): void {
        $gfx = sdlGfx()->drawLine(-10, -10, -2, -5, 0xFFFFFFFF);

        expect(sdlPaintedCount($gfx))->toBe(0);
    });
});

describe('rotation', function (): void {
    it('rejects rotations outside the quadrant range', function (): void {
        sdlGfx()->setRotation(4);
    })->throws(OutOfBoundsException::class);

    it('swaps the logical dimensions on 90° rotations', function (): void {
        $gfx = sdlGfx(16, 8);
        $gfx->rotation = 1;

        expect($gfx->width())->toBe(8)
            ->and($gfx->height())->toBe(16);
    });

    it('remaps pixels for a 90° rotation', function (): void {
        $gfx = sdlGfx();
        $gfx->rotation = 1;
        $gfx->drawPixel(1, 0, 0xFF0000FF);

        // rotation 1: physical x = W-1-y, physical y = x
        expect(sdlWord($gfx, 7, 1))->toBe(0xFF0000FF)
            ->and(sdlPaintedCount($gfx))->toBe(1);
    });

    it('remaps pixels for a 180° rotation', function (): void {
        $gfx = sdlGfx();
        $gfx->rotation = 2;
        $gfx->drawPixel(0, 0, 0xFF0000FF);

        expect(sdlWord($gfx, 7, 7))->toBe(0xFF0000FF);
    });

    it('keeps rotated fills rectangular', function (): void {
        $gfx = sdlGfx();
        $gfx->rotation = 1;
        $gfx->fillRect(0, 0, 3, 2, 0xFFFFFFFF);

        // Logical 3x2 at origin → physical 2x3 hugging the right edge
        expect(sdlPaintedCount($gfx))->toBe(6)
            ->and(sdlWord($gfx, 7, 0))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 6, 2))->toBe(0xFFFFFFFF);
    });
});

describe('shapes', function (): void {
    it('draws a symmetric circle outline', function (): void {
        $gfx = sdlGfx(9, 9)->drawCircle(4, 4, 3, 0xFFFFFFFF);

        expect(sdlWord($gfx, 4, 1))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 4, 7))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 1, 4))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 7, 4))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 4, 4))->toBe(0);
    });

    it('fills a circle including its center', function (): void {
        $gfx = sdlGfx(9, 9)->fillCircle(4, 4, 3, 0xFFFFFFFF);

        expect(sdlWord($gfx, 4, 4))->toBe(0xFFFFFFFF)
            ->and(sdlPaintedCount($gfx))->toBeGreaterThan(20);
    });

    it('fills a triangle', function (): void {
        $gfx = sdlGfx()->fillTriangle(0, 0, 7, 0, 0, 7, 0xFFFFFFFF);

        expect(sdlWord($gfx, 1, 1))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 7, 7))->toBe(0)
            ->and(sdlPaintedCount($gfx))->toBeGreaterThan(20);
    });

    it('draws round rects without corner overshoot', function (): void {
        $gfx = sdlGfx(12, 10)->drawRoundRect(0, 0, 12, 10, 3, 0xFFFFFFFF);

        expect(sdlWord($gfx, 0, 0))->toBe(0)
            ->and(sdlWord($gfx, 5, 0))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 0, 5))->toBe(0xFFFFFFFF)
            ->and(sdlPaintedCount($gfx))->toBeGreaterThan(20);
    });
});

describe('text', function (): void {
    it('renders classic-font text into pixels', function (): void {
        $gfx = sdlGfx(32, 16)
            ->setCursor(0, 0)
            ->setTextColor(0xFFFFFFFF, 0x000000FF)
            ->print('Hi');

        expect(sdlPaintedCount($gfx))->toBeGreaterThan(10);
    });

    it('measures text bounds for the classic font', function (): void {
        $bounds = sdlGfx(64, 16)->getTextBounds('Hi', 0, 0);

        expect($bounds)->toBe(['x1' => 0, 'y1' => 0, 'w' => 12, 'h' => 8]);
    });

    it('advances the cursor per glyph', function (): void {
        $gfx = sdlGfx(64, 16)->setTextColor(0xFFFFFFFF, 0x000000FF)->print('AB');

        expect($gfx->getCursorX())->toBe(12)
            ->and($gfx->getCursorY())->toBe(0);
    });
});

describe('bitmaps', function (): void {
    it('draws an MSB-first bitmap', function (): void {
        // 0b10000001 → pixels at x=0 and x=7
        $gfx = sdlGfx()->drawBitmap(0, 0, [0b10000001], 8, 1, 0xFFFFFFFF);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 7, 0))->toBe(0xFFFFFFFF)
            ->and(sdlPaintedCount($gfx))->toBe(2);
    });

    it('draws a color map with transparent holes', function (): void {
        $gfx = sdlGfx()->drawColorMap(0, 0, [0xFF0000FF, null, 0x00FF00FF, null], 2, 2);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFF0000FF)
            ->and(sdlWord($gfx, 0, 1))->toBe(0x00FF00FF)
            ->and(sdlPaintedCount($gfx))->toBe(2);
    });
});

describe('color mapping seam', function (): void {
    it('maps mono colors to black and white', function (): void {
        $spec = new FormatSpec(PixelFormat::MONO_VERTICAL_PAGE, BitDepth::B1);
        $gfx = Sdl3GFX::headless(4, 4, $spec)
            ->drawPixel(0, 0, 1)
            ->drawPixel(1, 0, 0);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFFFFFFFF)
            ->and(sdlWord($gfx, 1, 0))->toBe(0x000000FF);
    });

    it('expands RGB565 colors by bit replication', function (): void {
        $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16);
        $gfx = Sdl3GFX::headless(4, 4, $spec)->drawPixel(0, 0, 0xF800);

        expect(sdlWord($gfx, 0, 0))->toBe(0xFF0000FF);
    });

    it('treats RGB888 as the default mapping', function (): void {
        $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B24);
        $gfx = Sdl3GFX::headless(4, 4, $spec)->drawPixel(0, 0, 0x00FF00);

        expect(sdlWord($gfx, 0, 0))->toBe(0x00FF00FF);
    });

    it('round-trips working-spec colors through getPixel', function (): void {
        $spec = new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B16);
        $gfx = Sdl3GFX::headless(4, 4, $spec)->drawPixel(2, 1, 0x07E0);

        expect($gfx->buffer()->getPixel(2, 1))->toBe(0x07E0);
    });
});

describe('magic properties', function (): void {
    it('exposes width, height, and rotation', function (): void {
        $gfx = sdlGfx(16, 8);

        expect($gfx->width)->toBe(16)
            ->and($gfx->height)->toBe(8)
            ->and($gfx->rotation)->toBe(0);
    });

    it('throws on unknown properties', function (): void {
        sdlGfx()->nope;
    })->throws(Sdl3GFXException::class);
});
