<?php

namespace Microscrap\GFX\SDL3;

use Fabricate\Contracts\Displays\Display;
use Fabricate\Contracts\Framebuffers\Enums\BitDepth;
use Fabricate\Contracts\Framebuffers\Enums\Endianness;
use Fabricate\Contracts\Framebuffers\Enums\PixelFormat;
use Fabricate\Contracts\Framebuffers\Enums\RenderType;
use Fabricate\Contracts\Framebuffers\Framebuffer;
use Fabricate\Contracts\Framebuffers\SoftwareRenderableFramebuffer;
use Fabricate\Contracts\Rendering\GFXRenderer;
use Fabricate\Framebuffers\DataObjects\DamageGranularity;
use Fabricate\Framebuffers\DataObjects\DumpedBuffer;
use Fabricate\Framebuffers\FormatSpec;
use Fabricate\Framebuffers\Packers\PixelPackers;
use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\Bindings\SDL3\DataObjects\SDLSurfaceRef;
use Microscrap\Bindings\SDL3\Enums\PixelFormat as SdlPixelFormat;
use Microscrap\Bindings\SDL3\Error;
use Microscrap\Bindings\SDL3\Init;
use Microscrap\Bindings\SDL3\Render;
use Microscrap\Bindings\SDL3\Surface;

/**
 * A Framebuffer whose pixels live inside SDL rather than a PHP grid.
 *
 * Headless construction owns an off-screen RGBA8888 surface plus a software
 * renderer (works under SDL_Init(0), no window/video subsystem needed);
 * {@see attachedTo()} borrows an existing SDL renderer instead (windowed
 * mode) and never destroys it.
 *
 * The FormatSpec passed in is the *working* spec: it decides how GFX color
 * ints (mono 0/1, RGB565, RGB888, RGBA8888…) map onto SDL RGBA draw colors,
 * and what value {@see getPixel()} reports back. Dumps are packed back into
 * that working spec, allowing SDL drawing to target native embedded formats.
 */
class Sdl3Framebuffer implements SoftwareRenderableFramebuffer
{
    protected ?SDLSurfaceRef $surface = null;

    protected SDLRenderer $renderer;

    protected bool $owns_sdl_objects = true;

    /**
     * @throws Sdl3GFXException
     */
    public function __construct(
        protected FormatSpec $format_spec,
        protected int $width,
        protected int $height,
        ?SDLRenderer $attach_to = null,
    ) {
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
     * Windowed mode: draw through an SDL renderer someone else owns
     * (typically the one attached to an application window).
     *
     * @throws Sdl3GFXException
     */
    public static function attachedTo(SDLRenderer $renderer, FormatSpec $format_spec, int $width, int $height): static
    {
        return new static($format_spec, $width, $height, $renderer);
    }

    /**
     * The spec every dump leaves with: row-major RGBA8888, red byte first.
     */
    public static function rgbaSpec(): FormatSpec
    {
        return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, endianness: Endianness::MSB);
    }

    public function formatSpec(): FormatSpec
    {
        return $this->format_spec;
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

    // -- Physical-space SDL primitives ----------------------------------------

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
        // ext-sdl3 rects are flat [x, y, w, h] lists
        Render::renderFillRect($this->renderer, [$x, $y, $width, $height]);

        return $this;
    }

    public function clear(int $color): static
    {
        $this->applyColor($color);
        Render::renderClear($this->renderer);

        return $this;
    }

    /**
     * Flush queued draw commands so read-backs observe every prior call.
     */
    public function present(): static
    {
        Render::renderPresent($this->renderer);

        return $this;
    }

    // -- Framebuffer contract --------------------------------------------------

    public function viewportWidth(): int
    {
        return $this->width;
    }

    public function viewportHeight(): int
    {
        return $this->height;
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

    /**
     * @param  array<int, array{0: int, 1: int, 2: int}>  $pixels
     */
    public function setPixels(array $pixels): static
    {
        foreach ($pixels as [$x, $y, $value]) {
            $this->setPixel($x, $y, $value);
        }

        return $this;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $coordinates
     */
    public function setRegion(array $coordinates, int $value): static
    {
        foreach ($coordinates as [$x, $y]) {
            $this->setPixel($x, $y, $value);
        }

        return $this;
    }

    public function setSegment(int $x, int $y, int $width, int $height, int $color): static
    {
        if (($width <= 0) || ($height <= 0)) {
            return $this;
        }

        return $this->fillRectRaw($x, $y, $width, $height, $color);
    }

    public function blitFrom(Framebuffer $source, int $offset_x = 0, int $offset_y = 0): Framebuffer
    {
        for ($y = 0; $y < $source->viewportHeight(); $y++) {
            for ($x = 0; $x < $source->viewportWidth(); $x++) {
                $this->setPixel($offset_x + $x, $offset_y + $y, $source->getPixel($x, $y));
            }
        }

        return $this;
    }

    public function blitTo(Framebuffer $target, int $offset_x = 0, int $offset_y = 0): Framebuffer
    {
        return $target->blitFrom($this, $offset_x, $offset_y);
    }

    // -- Read-back / export ----------------------------------------------------

    /**
     * The whole target as flat row-major 0xRRGGBBAA words.
     *
     * @return array<int, int>
     */
    public function readPixelWords(): array
    {
        $this->present();

        if ($this->isHeadless()) {
            $result = Surface::readSurfacePixels($this->surface);

            return array_values($result['pixels_data'] ?? []);
        }

        $result = Render::renderReadPixels($this->renderer);

        return array_values($result['pixels']['data'] ?? []);
    }

    /**
     * @return array<int, DumpedBuffer>
     */
    public function dump(): array
    {
        $words = $this->readPixelWords();

        // Working B32 ROW_MAJOR already matches dump layout — pack words
        // straight to bytes and skip the width×height nested array.
        if (
            $this->format_spec->bit_depth === BitDepth::B32
            && $this->format_spec->pixel_format === PixelFormat::ROW_MAJOR
            && ($this->format_spec->endianness ?? Endianness::MSB) === Endianness::MSB
        ) {
            $bytes = [];

            foreach ($words as $word) {
                $bytes[] = ($word >> 24) & 0xFF;
                $bytes[] = ($word >> 16) & 0xFF;
                $bytes[] = ($word >> 8) & 0xFF;
                $bytes[] = $word & 0xFF;
            }

            return [
                new DumpedBuffer(
                    RenderType::FULL,
                    $this->format_spec,
                    $bytes,
                    width: $this->width,
                    height: $this->height,
                ),
            ];
        }

        $pixels = [];

        for ($y = 0; $y < $this->height; $y++) {
            $row = [];

            for ($x = 0; $x < $this->width; $x++) {
                $row[] = $this->unmapColor($words[($y * $this->width) + $x] ?? 0);
            }

            $pixels[] = $row;
        }

        return [
            new DumpedBuffer(
                RenderType::FULL,
                $this->format_spec,
                PixelPackers::resolve($this->format_spec->pixel_format)->pack(
                    $pixels,
                    $this->format_spec,
                    $this->width,
                    $this->height,
                ),
                width: $this->width,
                height: $this->height,
            ),
        ];
    }

    /**
     * Windowed (attached) buffers already draw into the display's SDL
     * renderer — present in place and skip the readback→pack→upload path.
     *
     * @return array<int, DumpedBuffer>
     */
    public function flush(): array
    {
        if (! $this->isHeadless()) {
            $this->present();

            return [];
        }

        return $this->dump();
    }

    /**
     * Rebind a headless buffer onto a windowed SDL3 panel's live renderer.
     *
     * Called by PendingVisualPresentation when the display panel exposes
     * renderer() (SDL3Window). Returns $this when already attached or when
     * the display has no SDL renderer to share.
     */
    public function bindDisplaySurface(Display $display): static
    {
        if (! $this->isHeadless()) {
            return $this;
        }

        if (! method_exists($display, 'panel')) {
            return $this;
        }

        $panel = $display->panel();

        if (! is_object($panel) || ! method_exists($panel, 'renderer')) {
            return $this;
        }

        $renderer = $panel->renderer();

        if (! $renderer instanceof SDLRenderer) {
            return $this;
        }

        return static::attachedTo(
            $renderer,
            $this->format_spec,
            $this->width,
            $this->height,
        );
    }

    public function supportsDisplay(Display $display): bool
    {
        return true;
    }

    public function supportsRenderer(GFXRenderer $renderer): bool
    {
        return true;
    }

    /**
     * Dumps are always RenderType::FULL and attached buffers present natively,
     * so there is no sub-surface transmit unit to snap damage to.
     */
    public function damageGranularity(): DamageGranularity
    {
        return DamageGranularity::wholeSurface($this->width, $this->height);
    }

    /**
     * Headless buffers keep their offscreen surface, but SDL leaves a windowed
     * renderer's backbuffer undefined once presented, so retained partial
     * repaint cannot be trusted there.
     */
    public function preservesContentsOnPresent(): bool
    {
        return $this->isHeadless();
    }

    // -- Color seam --------------------------------------------------------------

    protected function applyColor(int $color): void
    {
        [$r, $g, $b, $a] = $this->mapColor($color);

        Render::setRenderDrawColor($this->renderer, $r, $g, $b, $a);
    }

    /**
     * GFX color int (in working-spec terms) → RGBA draw color.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public function mapColor(int $color): array
    {
        if ($this->isMonochrome()) {
            return ($color === 0) ? [0, 0, 0, 255] : [255, 255, 255, 255];
        }

        return match ($this->format_spec->bit_depth) {
            BitDepth::B8 => [$color & 0xFF, $color & 0xFF, $color & 0xFF, 255],
            BitDepth::B12 => $this->expandRgb444($color),
            BitDepth::B16 => $this->expandRgb565($color),
            BitDepth::B18 => $this->expandRgb666($color),
            BitDepth::B32 => [($color >> 24) & 0xFF, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF],
            default => [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF, 255],
        };
    }

    /**
     * RGBA8888 word read back from SDL → GFX color int in working-spec terms.
     */
    public function unmapColor(int $rgba_word): int
    {
        $r = ($rgba_word >> 24) & 0xFF;
        $g = ($rgba_word >> 16) & 0xFF;
        $b = ($rgba_word >> 8) & 0xFF;

        if ($this->isMonochrome()) {
            return (($r > 127) || ($g > 127) || ($b > 127)) ? 1 : 0;
        }

        return match ($this->format_spec->bit_depth) {
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
        return ($this->format_spec->bit_depth === BitDepth::B1)
            || ($this->format_spec->pixel_format === PixelFormat::MONO_VERTICAL_PAGE)
            || ($this->format_spec->pixel_format === PixelFormat::MONO_HORIZONTAL);
    }

    /**
     * 5/6-bit channels expanded by bit replication (31 → 255, 63 → 255) —
     * identical math to the framework's Rgb565ToRgbaEncoder.
     *
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
     * Left-justified RGB666 word → RGBA draw color.
     *
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
