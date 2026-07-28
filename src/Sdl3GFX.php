<?php

namespace Microscrap\GFX\SDL3;

use Fabricate\Contracts\Framebuffers\Framebuffer;
use Fabricate\Contracts\Rendering\RenderingException;
use Fabricate\Framebuffers\DataObjects\DumpedBuffer;
use Fabricate\Framebuffers\FormatSpec;
use Fabricate\Rendering\Renderer2D;
use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\GFX\SDL3\Concerns\Sdl3GFXAPI;

/**
 * SDL3-native renderer. Drivers may construct unbound; factories attach the
 * DisplayComponent-owned Sdl3Framebuffer via {@see useFramebuffer()}.
 *
 * @property-read int $height
 * @property-read int $width
 */
class SDL3Gfx extends Renderer2D
{
    use Sdl3GFXAPI;

    protected ?Sdl3Framebuffer $buffer = null;

    public function __construct(?Framebuffer $buffer = null)
    {
        if (! is_null($buffer)) {
            $this->useFramebuffer($buffer);
        }
    }

    /**
     * @throws Sdl3GFXException
     */
    public static function headless(int $width, int $height, ?FormatSpec $format_spec = null): static
    {
        return new static(new Sdl3Framebuffer($format_spec ?? Sdl3Framebuffer::rgbaSpec(), $width, $height));
    }

    /**
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

    public function useFramebuffer(Framebuffer $framebuffer): static
    {
        if (! $this->supportsFramebuffer($framebuffer)) {
            throw Sdl3GFXException::unsupportedFramebuffer($framebuffer::class);
        }

        $this->buffer = $framebuffer;

        return $this;
    }

    public function supportsFramebuffer(Framebuffer $framebuffer): bool
    {
        return $framebuffer instanceof Sdl3Framebuffer;
    }

    public function drawPixel(int $x, int $y, int $color): static
    {
        if (($x < 0) || ($y < 0) || ($x >= $this->width()) || ($y >= $this->height())) {
            return $this;
        }

        [$x, $y] = $this->applyRotation($x, $y);
        $this->requireBuffer()->point($x, $y, $color);

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
        $this->requireBuffer()->clear($color);

        return $this;
    }

    public function present(): static
    {
        $this->requireBuffer()->present();

        return $this;
    }

    public function buffer(): Sdl3Framebuffer
    {
        return $this->requireBuffer();
    }

    /**
     * @return array<int, DumpedBuffer>
     */
    public function render(): array
    {
        return $this->requireBuffer()->dump();
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

    protected function requireBuffer(): Sdl3Framebuffer
    {
        if (is_null($this->buffer)) {
            throw RenderingException::framebufferNotAttached(static::class);
        }

        return $this->buffer;
    }
}
