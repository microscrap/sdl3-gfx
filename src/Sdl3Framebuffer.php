<?php

namespace Microscrap\GFX\SDL3;

use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\Bindings\SDL3\DataObjects\SDLSurfaceRef;
use Microscrap\Bindings\SDL3\Enums\PixelFormat as SdlPixelFormat;
use Microscrap\Bindings\SDL3\Error;
use Microscrap\Bindings\SDL3\Init;
use Microscrap\Bindings\SDL3\Render;
use Microscrap\Bindings\SDL3\Surface;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DamageGranularity;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DumpedBuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\BitDepth;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\Endianness;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\RenderType;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Framebuffers\DeferredFramebuffer;

/**
 * Deferred (host-backed) framebuffer: pixels live in SDL, not a PHP PixelStore.
 *
 * Default construction is headless — owns an off-screen RGBA8888 surface plus a
 * software renderer via microscrap/sdl3 (no window / video subsystem required).
 * {@see attachedTo()} borrows an existing SDL renderer (windowed) and never
 * destroys it.
 *
 * Soft Managed drivers (`full` / `dirty` / `page`) are a different lane.
 */
class Sdl3Framebuffer extends DeferredFramebuffer
{
    protected ?SDLSurfaceRef $surface = null;

    protected SDLRenderer $renderer;

    protected bool $owns_sdl_objects = true;

    /**
     * @throws Sdl3GFXException
     */
    public function __construct(
        int $width,
        int $height,
        FormatSpec $format_spec,
        ?SDLRenderer $attach_to = null,
    ) {
        parent::__construct($width, $height, $format_spec);

        if (! is_null($attach_to)) {
            $this->renderer = $attach_to;
            $this->owns_sdl_objects = false;

            return;
        }

        Init::init(0);

        $surface = Surface::createSurface($width, $height, SdlPixelFormat::SDL_PIXELFORMAT_RGBA8888);
        if (is_null($surface)) {
            throw Sdl3GFXException::surfaceCreationFailed($width, $height, Error::getError());
        }

        $renderer = Render::createSoftwareRenderer($surface->ptr);
        if (is_null($renderer)) {
            Surface::destroySurface($surface);

            throw Sdl3GFXException::rendererCreationFailed(Error::getError());
        }

        $this->surface = $surface;
        $this->renderer = $renderer;
    }

    public function __destruct()
    {
        if (! $this->owns_sdl_objects) {
            return;
        }

        Render::destroyRenderer($this->renderer);

        if (! is_null($this->surface)) {
            Surface::destroySurface($this->surface);
        }
    }

    /**
     * Headless factory: SDL soft surface + software renderer (no window).
     *
     * @throws Sdl3GFXException
     */
    public static function sized(int $width, int $height, FormatSpec $host_format): static
    {
        return new static($width, $height, $host_format);
    }

    /**
     * Windowed mode: draw through an SDL renderer someone else owns.
     *
     * @throws Sdl3GFXException
     */
    public static function attachedTo(
        SDLRenderer $renderer,
        FormatSpec $format_spec,
        int $width,
        int $height,
    ): static {
        return new static($width, $height, $format_spec, $renderer);
    }

    /**
     * Default working / dump layout for SDL RGBA8888 soft surfaces.
     */
    public static function rgbaSpec(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, endianness: Endianness::MSB);
    }

    public function sdlRenderer(): SDLRenderer
    {
        return $this->renderer;
    }

    public function sdlSurface(): ?SDLSurfaceRef
    {
        return $this->surface;
    }

    public function isHeadless(): bool
    {
        return ! is_null($this->surface);
    }

    public function point(int $x, int $y, int $color): static
    {
        $this->applyColor($color);
        Render::renderPoint($this->renderer, (float) $x, (float) $y);

        return $this;
    }

    public function line(int $x0, int $y0, int $x1, int $y1, int $color): static
    {
        $this->applyColor($color);
        Render::renderLine($this->renderer, (float) $x0, (float) $y0, (float) $x1, (float) $y1);

        return $this;
    }

    public function fillRectRaw(int $x, int $y, int $width, int $height, int $color): static
    {
        $this->applyColor($color);
        Render::renderFillRect($this->renderer, [$x, $y, $width, $height]);

        return $this;
    }

    /**
     * Engine clear of the whole drawable (replaces the old clear(int $color) API).
     */
    public function fill(int $color): static
    {
        $this->applyColor($color);
        Render::renderClear($this->renderer);

        return $this;
    }

    public function present(): static
    {
        Render::renderPresent($this->renderer);

        return $this;
    }

    public function getPixel(int $x, int $y): int
    {
        if (($x < 0) || ($y < 0) || ($x >= $this->width) || ($y >= $this->height)) {
            return 0;
        }

        $words = $this->readPixelWords();

        return $this->unmapColor($words[($y * $this->width) + $x] ?? 0);
    }

    public function setPixel(int $x, int $y, int $value): static
    {
        if (($x < 0) || ($y < 0) || ($x >= $this->width) || ($y >= $this->height)) {
            return $this;
        }

        return $this->point($x, $y, $value);
    }

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static
    {
        if (($width <= 0) || ($height <= 0)) {
            return $this;
        }

        return $this->fillRectRaw($x, $y, $width, $height, $color);
    }

    /**
     * @return array<int, int>
     */
    public function readPixelWords(): array
    {
        $this->present();

        if ($this->isHeadless()) {
            $result = Surface::readSurfacePixels($this->surface);

            if (isset($result['pixels_data']) && is_array($result['pixels_data'])) {
                return array_values($result['pixels_data']);
            }

            return array_values($result);
        }

        $result = Render::renderReadPixels($this->renderer);

        return array_values($result['pixels']['data'] ?? $result['pixels'] ?? []);
    }

    public function dump(?int $layer = null): string
    {
        return $this->packWords($this->readPixelWords(), $this->host_format);
    }

    /**
     * @return string|array<int, DumpedBuffer|int>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array
    {
        if (! $this->isHeadless()) {
            $this->present();

            return $as_array ? [] : '';
        }

        $bytes = $this->packWords($this->readPixelWords(), $spec);

        if (! $as_array) {
            return $bytes;
        }

        return [
            new DumpedBuffer(
                RenderType::FULL,
                $spec,
                $bytes,
                width: $this->width,
                height: $this->height,
            ),
        ];
    }

    public function damageGranularity(): DamageGranularity
    {
        return DamageGranularity::wholeSurface($this->width, $this->height);
    }

    public function preservesContentsOnPresent(): bool
    {
        return $this->isHeadless();
    }

    protected function applyColor(int $color): void
    {
        [$r, $g, $b, $a] = $this->mapColor($color);

        Render::setRenderDrawColor($this->renderer, $r, $g, $b, $a);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public function mapColor(int $color): array
    {
        if ($this->isMonochrome()) {
            return ($color === 0) ? [0, 0, 0, 255] : [255, 255, 255, 255];
        }

        return match ($this->host_format->bit_depth) {
            BitDepth::B8 => [$color & 0xFF, $color & 0xFF, $color & 0xFF, 255],
            BitDepth::B12 => $this->expandRgb444($color),
            BitDepth::B16 => $this->expandRgb565($color),
            BitDepth::B18 => $this->expandRgb666($color),
            BitDepth::B32 => [($color >> 24) & 0xFF, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF],
            default => [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, 255],
        };
    }

    public function unmapColor(int $rgba_word): int
    {
        $r = ($rgba_word >> 24) & 0xFF;
        $g = ($rgba_word >> 16) & 0xFF;
        $b = ($rgba_word >> 8) & 0xFF;

        if ($this->isMonochrome()) {
            return (($r > 127) || ($g > 127) || ($b > 127)) ? 1 : 0;
        }

        return match ($this->host_format->bit_depth) {
            BitDepth::B8 => $r,
            BitDepth::B12 => (($r >> 4) << 8) | (($g >> 4) << 4) | ($b >> 4),
            BitDepth::B16 => (($r >> 3) << 11) | (($g >> 2) << 5) | ($b >> 3),
            BitDepth::B18 => (($r & 0xFC) << 16) | (($g & 0xFC) << 8) | ($b & 0xFC),
            BitDepth::B32 => $rgba_word,
            default => ($r << 16) | ($g << 8) | $b,
        };
    }

    protected function isMonochrome(): bool
    {
        return ($this->host_format->bit_depth === BitDepth::B1)
            || ($this->host_format->pixel_format === PixelFormat::MONO_VERTICAL_PAGE)
            || ($this->host_format->pixel_format === PixelFormat::MONO_HORIZONTAL);
    }

    /**
     * @param  array<int, int>  $words
     */
    protected function packWords(array $words, FormatSpec $spec): string
    {
        if (
            $spec->bit_depth === BitDepth::B32
            && $spec->pixel_format === PixelFormat::ROW_MAJOR
            && ($spec->endianness ?? Endianness::MSB) === Endianness::MSB
        ) {
            $bytes = '';

            foreach ($words as $word) {
                $bytes .= chr(($word >> 24) & 0xFF)
                    .chr(($word >> 16) & 0xFF)
                    .chr(($word >> 8) & 0xFF)
                    .chr($word & 0xFF);
            }

            return $bytes;
        }

        $bytes = '';

        foreach ($words as $word) {
            $color = $this->unmapColor($word);
            $bytes .= $this->packColorBytes($color, $spec);
        }

        return $bytes;
    }

    protected function packColorBytes(int $color, FormatSpec $spec): string
    {
        return match ($spec->bit_depth) {
            BitDepth::B8 => chr($color & 0xFF),
            BitDepth::B16 => (($spec->endianness ?? Endianness::MSB) === Endianness::LSB)
                ? chr($color & 0xFF).chr(($color >> 8) & 0xFF)
                : chr(($color >> 8) & 0xFF).chr($color & 0xFF),
            BitDepth::B24 => chr(($color >> 16) & 0xFF).chr(($color >> 8) & 0xFF).chr($color & 0xFF),
            BitDepth::B32 => chr(($color >> 24) & 0xFF)
                .chr(($color >> 16) & 0xFF)
                .chr(($color >> 8) & 0xFF)
                .chr($color & 0xFF),
            default => chr(($color >> 16) & 0xFF).chr(($color >> 8) & 0xFF).chr($color & 0xFF),
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function expandRgb444(int $color): array
    {
        $r4 = ($color >> 8) & 0xF;
        $g4 = ($color >> 4) & 0xF;
        $b4 = $color & 0xF;

        return [
            ($r4 << 4) | $r4,
            ($g4 << 4) | $g4,
            ($b4 << 4) | $b4,
            255,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function expandRgb565(int $color): array
    {
        $r5 = ($color >> 11) & 0x1F;
        $g6 = ($color >> 5) & 0x3F;
        $b5 = $color & 0x1F;

        return [
            ($r5 << 3) | ($r5 >> 2),
            ($g6 << 2) | ($g6 >> 4),
            ($b5 << 3) | ($b5 >> 2),
            255,
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function expandRgb666(int $color): array
    {
        $r6 = ($color >> 18) & 0x3F;
        $g6 = ($color >> 10) & 0x3F;
        $b6 = ($color >> 2) & 0x3F;

        return [
            ($r6 << 2) | ($r6 >> 4),
            ($g6 << 2) | ($g6 >> 4),
            ($b6 << 2) | ($b6 >> 4),
            255,
        ];
    }
}
