<?php

namespace Microscrap\GFX\SDL3;

use BareMetal\Contracts\Framebuffers\DTO\DumpedBuffer;
use BareMetal\Contracts\Framebuffers\DTO\FormatSpec;
use BareMetal\Contracts\Framebuffers\Framebuffer;
use BareMetal\Framebuffers\FormatSpecFramebuffer;
use BareMetal\GFX\Renderer2D;
use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\GFX\SDL3\Concerns\Sdl3GFXAPI;

/**
 * SDL3-native renderer: the same drawing API surface as the software
 * PhpdafruitGFX, but every primitive resolves to SDL render calls against an
 * off-screen surface (headless) or a borrowed window renderer (windowed).
 *
 * Rects, fills, and axis-aligned lines map straight onto SDLRenderFillRect /
 * SDLRenderLine; circles, round rects, triangles, text, and bitmaps reuse the
 * Adafruit-style algorithms over drawPixel, so both GFX drivers rasterize the
 * same logical pixel grid.
 *
 * @property-read int $height
 * @property-read int $width
 * @property int $rotation
 */
class Sdl3GFX extends Renderer2D
{
    use Sdl3GFXAPI;

    protected Sdl3Framebuffer $buffer;

    /**
     * Accepts any framework framebuffer for DisplayComponent compatibility:
     * an Sdl3Framebuffer is adopted as-is, anything else donates its
     * FormatSpec and dimensions to a fresh headless SDL target.
     *
     * @throws Sdl3GFXException
     */
    public function __construct(Framebuffer $buffer)
    {
        $this->buffer = ($buffer instanceof Sdl3Framebuffer)
            ? $buffer
            : static::adoptForeignBuffer($buffer);
    }

    /**
     * @throws Sdl3GFXException
     */
    protected static function adoptForeignBuffer(Framebuffer $buffer): Sdl3Framebuffer
    {
        $format_spec = ($buffer instanceof FormatSpecFramebuffer)
            ? $buffer->formatSpec()
            : Sdl3Framebuffer::rgbaSpec();

        return new Sdl3Framebuffer($format_spec, $buffer->viewportWidth(), $buffer->viewportHeight());
    }

    /**
     * Off-screen surface + software renderer; works under SDL_Init(0), no
     * window or video subsystem required.
     *
     * @throws Sdl3GFXException
     */
    public static function headless(int $width, int $height, ?FormatSpec $format_spec = null): static
    {
        return new static(new Sdl3Framebuffer($format_spec ?? Sdl3Framebuffer::rgbaSpec(), $width, $height));
    }

    /**
     * Draw through an existing SDL renderer (typically one attached to a
     * window); the caller keeps ownership of the renderer's lifetime.
     *
     * @throws Sdl3GFXException
     */
    public static function windowed(SDLRenderer $renderer, int $width, int $height, ?FormatSpec $format_spec = null): static
    {
        return new static(Sdl3Framebuffer::attachedTo(
            $renderer,
            $format_spec ?? Sdl3Framebuffer::rgbaSpec(),
            $width,
            $height
        ));
    }

    public function drawPixel(int $x, int $y, int $color): static
    {
        // Bounds check in logical coordinates
        if (($x < 0) || ($y < 0) || ($x >= $this->width) || ($y >= $this->height)) {
            return $this;
        }

        [$x, $y] = $this->applyRotation($x, $y);
        $this->buffer->point($x, $y, $color);

        return $this;
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels
     */
    public function drawPixels(array $pixels): static
    {
        foreach ($pixels as [$x, $y, $color]) {
            $this->drawPixel($x, $y, $color);
        }

        return $this;
    }

    public function drawHLine(int $x, int $y, int $w, int $color): static
    {
        return $this->drawHorizontalLine($x, $y, $w, $color);
    }

    public function drawVLine(int $x, int $y, int $h, int $color): static
    {
        return $this->drawVerticalLine($x, $y, $h, $color);
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int, 3: int, 4: int}>  $lines
     */
    public function drawLines(array $lines): static
    {
        foreach ($lines as [$x0, $y0, $x1, $y1, $color]) {
            $this->drawLine($x0, $y0, $x1, $y1, $color);
        }

        return $this;
    }

    public function fill(int $color): static
    {
        $this->buffer->clear($color);

        return $this;
    }

    /**
     * Flush queued SDL draw commands (present the frame in windowed mode).
     */
    public function present(): static
    {
        $this->buffer->present();

        return $this;
    }

    public function buffer(): Sdl3Framebuffer
    {
        return $this->buffer;
    }

    /**
     * Read the SDL target back and emit it as one FULL ROW_MAJOR B32 frame —
     * the "SDL buffer flushed to an embedded display" path.
     *
     * @return array<int, DumpedBuffer>
     */
    public function render(): array
    {
        return $this->buffer->dump();
    }

    /**
     * @throws Sdl3GFXException
     */
    public static function preferredFramebuffer(FormatSpec $format_spec, int $width, int $height): Framebuffer
    {
        return new Sdl3Framebuffer($format_spec, $width, $height);
    }

    /**
     * @throws Sdl3GFXException
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'rotation' => $this->getRotation(),
            'height' => $this->height(),
            'width' => $this->width(),
            default => throw Sdl3GFXException::invalidProperty($name, static::class),
        };
    }

    /**
     * @throws Sdl3GFXException
     */
    public function __set(string $name, mixed $value): void
    {
        match ($name) {
            'rotation' => $this->setRotation((int) $value),
            default => throw Sdl3GFXException::invalidProperty($name, static::class),
        };
    }
}
