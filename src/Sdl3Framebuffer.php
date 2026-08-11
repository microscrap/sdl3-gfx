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
use ScrapyardIO\Tubes\Framebuffers\PixelStore;

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
     * Inclusive dirty rectangles [left, top, right, bottom] — coalesced at flush.
     *
     * @var array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    protected array $dirty_regions = [];

    /**
     * When > 0, markDirty unions into {@see $deferred_dirty_union} instead of
     * appending one rect per primitive (fillCircle hlines thrash coalesce).
     */
    protected int $dirty_defer_depth = 0;

    /**
     * Inclusive union while {@see deferDirty()} is active.
     *
     * @var array{0: int, 1: int, 2: int, 3: int}|null
     */
    protected ?array $deferred_dirty_union = null;

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
        $this->markDirty($x, $y, $x, $y);

        return $this;
    }

    public function line(int $x0, int $y0, int $x1, int $y1, int $color): static
    {
        $this->applyColor($color);
        Render::renderLine($this->renderer, (float) $x0, (float) $y0, (float) $x1, (float) $y1);
        $this->markDirty(
            min($x0, $x1),
            min($y0, $y1),
            max($x0, $x1),
            max($y0, $y1),
        );

        return $this;
    }

    public function fillRectRaw(int $x, int $y, int $width, int $height, int $color): static
    {
        $this->applyColor($color);
        Render::renderFillRect($this->renderer, [$x, $y, $width, $height]);
        if (($width > 0) && ($height > 0)) {
            $this->markDirty($x, $y, ($x + $width) - 1, ($y + $height) - 1);
        }

        return $this;
    }

    /**
     * Engine clear of the whole drawable (replaces the old clear(int $color) API).
     */
    public function fill(int $color): static
    {
        $this->applyColor($color);
        Render::renderClear($this->renderer);
        $this->markAllDirty();

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

        return $this->readSurfacePixelWords();
    }

    /**
     * Read host RGBA words after a present (no second present).
     *
     * @return array<int, int>
     */
    protected function readSurfacePixelWords(): array
    {
        if ($this->isHeadless()) {
            $result = Surface::readSurfacePixels($this->surface);

            return $this->normalizePixelWords(is_array($result) ? $result : []);
        }

        $result = Render::renderReadPixels($this->renderer);

        return $this->normalizePixelWords(is_array($result) ? $result : []);
    }

    public function dump(?int $layer = null): string
    {
        return $this->packRgbaWords($this->readPixelWords(), $this->width, $this->height, $this->host_format);
    }

    /**
     * Flush for PanelIC (headless) — host RGBA8888 → target FormatSpec.
     *
     * Matching B32 host → passthrough bytes. ROW_MAJOR B16 (ST7789 RGB565) uses a
     * tight pack loop — not per-pixel {@see PixelStore} (that path crushed Pi0 FPS).
     * Dirty rects emit {@see RenderType::PARTIAL} when damage is sparse.
     *
     * @return string|array<int, DumpedBuffer|int>
     */
    public function flush(FormatSpec $spec, bool $as_array = false): string|array
    {
        if (! $this->isHeadless()) {
            $this->present();

            return $as_array ? [] : '';
        }

        if ($this->dirty_regions === []) {
            return $as_array ? [] : '';
        }

        $regions = $this->coalesceDirtyRegions($this->dirty_regions);
        $this->dirty_regions = [];

        $whole = count($regions) === 1
            && $regions[0][0] === 0
            && $regions[0][1] === 0
            && $regions[0][2] === ($this->width - 1)
            && $regions[0][3] === ($this->height - 1);

        // One present + at most one full surface read for this flush.
        $this->present();
        $fullWords = null;

        if ($whole) {
            $fullWords = $this->readSurfacePixelWords();
            $bytes = $this->packRgbaWords($fullWords, $this->width, $this->height, $spec);

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

        $updates = [];

        foreach ($regions as [$left, $top, $right, $bottom]) {
            $regionW = ($right - $left) + 1;
            $regionH = ($bottom - $top) + 1;
            $words = $this->readPixelWordsRegion($left, $top, $regionW, $regionH, $fullWords);
            $bytes = $this->packRgbaWords($words, $regionW, $regionH, $spec);

            $updates[] = new DumpedBuffer(
                RenderType::PARTIAL,
                $spec,
                $bytes,
                origin_x: $left,
                origin_y: $top,
                width: $regionW,
                height: $regionH,
            );
        }

        if (! $as_array) {
            $joined = '';
            foreach ($updates as $frame) {
                $joined .= $frame->raw_data;
            }

            return $joined;
        }

        return $updates;
    }

    /**
     * Collapse many primitive dirty marks into one bbox (circles / text runs).
     *
     * @param  callable(): void  $draw
     */
    public function deferDirty(callable $draw): static
    {
        $this->dirty_defer_depth++;

        try {
            $draw();
        } finally {
            $this->dirty_defer_depth--;

            if ($this->dirty_defer_depth === 0 && ! is_null($this->deferred_dirty_union)) {
                [$left, $top, $right, $bottom] = $this->deferred_dirty_union;
                $this->deferred_dirty_union = null;
                $this->markDirty($left, $top, $right, $bottom);
            }
        }

        return $this;
    }

    public function damageGranularity(): DamageGranularity
    {
        // Headless PanelIC path tracks dirty rects → pixel damage (PARTIAL flush).
        // Window-attached surfaces stay whole-surface (native present, no PHP dump).
        if ($this->isHeadless()) {
            return DamageGranularity::pixel($this->width, $this->height);
        }

        return DamageGranularity::wholeSurface($this->width, $this->height);
    }

    public function preservesContentsOnPresent(): bool
    {
        return $this->isHeadless();
    }

    public function markAllDirty(): static
    {
        $this->deferred_dirty_union = null;
        $this->dirty_regions = [[0, 0, $this->width - 1, $this->height - 1]];

        return $this;
    }

    protected function markDirty(int $left, int $top, int $right, int $bottom): void
    {
        $left = max(0, $left);
        $top = max(0, $top);
        $right = min($this->width - 1, $right);
        $bottom = min($this->height - 1, $bottom);

        if (($left > $right) || ($top > $bottom)) {
            return;
        }

        if ($this->dirty_defer_depth > 0) {
            if (is_null($this->deferred_dirty_union)) {
                $this->deferred_dirty_union = [$left, $top, $right, $bottom];

                return;
            }

            $this->deferred_dirty_union[0] = min($this->deferred_dirty_union[0], $left);
            $this->deferred_dirty_union[1] = min($this->deferred_dirty_union[1], $top);
            $this->deferred_dirty_union[2] = max($this->deferred_dirty_union[2], $right);
            $this->deferred_dirty_union[3] = max($this->deferred_dirty_union[3], $bottom);

            return;
        }

        $this->dirty_regions[] = [$left, $top, $right, $bottom];
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2: int, 3: int}>  $regions
     * @return array<int, array{0: int, 1: int, 2: int, 3: int}>
     */
    protected function coalesceDirtyRegions(array $regions): array
    {
        if ($regions === []) {
            return [];
        }

        $pending = array_values($regions);
        $merged = [];

        while ($pending !== []) {
            [$left, $top, $right, $bottom] = array_shift($pending);
            $grew = true;

            while ($grew) {
                $grew = false;
                $next = [];

                foreach ($pending as $region) {
                    [$region_left, $region_top, $region_right, $region_bottom] = $region;
                    $touches = ($left <= $region_right + 1) && ($region_left <= $right + 1)
                        && ($top <= $region_bottom + 1) && ($region_top <= $bottom + 1);

                    if ($touches) {
                        $left = min($left, $region_left);
                        $top = min($top, $region_top);
                        $right = max($right, $region_right);
                        $bottom = max($bottom, $region_bottom);
                        $grew = true;
                    } else {
                        $next[] = $region;
                    }
                }

                $pending = $next;
            }

            $merged[] = [$left, $top, $right, $bottom];
        }

        return $merged;
    }

    /**
     * @param  array<int, int>|null  $cachedFull  Optional full-surface words (already presented).
     * @return array<int, int>
     */
    protected function readPixelWordsRegion(
        int $x,
        int $y,
        int $width,
        int $height,
        ?array &$cachedFull = null,
    ): array {
        $expected = $width * $height;
        $result = Render::renderReadPixels($this->renderer, [$x, $y, $width, $height]);
        $words = $this->normalizePixelWords($result);

        if (count($words) === $expected) {
            return $words;
        }

        // Software surface path: one full read shared across regions in this flush.
        if (is_null($cachedFull)) {
            $cachedFull = $this->readSurfacePixelWords();
        }

        $sliced = [];

        for ($row = 0; $row < $height; $row++) {
            $src = (($y + $row) * $this->width) + $x;
            for ($col = 0; $col < $width; $col++) {
                $sliced[] = $cachedFull[$src + $col] ?? 0;
            }
        }

        return $sliced;
    }

    /**
     * @param  array<int, mixed>  $result
     * @return array<int, int>
     */
    protected function normalizePixelWords(array $result): array
    {
        if (isset($result['pixels_data']) && is_array($result['pixels_data'])) {
            return array_values($result['pixels_data']);
        }

        if (isset($result['pixels']['data']) && is_array($result['pixels']['data'])) {
            return array_values($result['pixels']['data']);
        }

        if (isset($result['pixels']) && is_array($result['pixels']) && array_is_list($result['pixels'])) {
            return array_values($result['pixels']);
        }

        if (array_is_list($result)) {
            return array_values($result);
        }

        return [];
    }

    /**
     * Pack SDL RGBA8888 words (0xRRGGBBAA) into a target FormatSpec byte stream.
     *
     * Matching B32 MSB → chunked pack. ROW_MAJOR B16 → tight RGB565 (PixelColorPack
     * math) — avoid per-pixel PixelStore on the PanelIC hot path.
     *
     * @param  array<int, int>  $words
     */
    protected function packRgbaWords(array $words, int $width, int $height, FormatSpec $spec): string
    {
        if (
            $spec->pixel_format === PixelFormat::ROW_MAJOR
            && $spec->bit_depth === BitDepth::B32
            && ($spec->endianness ?? Endianness::MSB) === Endianness::MSB
        ) {
            return $this->packWordChunks($words, 'N*');
        }

        if (
            $spec->pixel_format === PixelFormat::ROW_MAJOR
            && $spec->bit_depth === BitDepth::B16
        ) {
            $msb = ($spec->endianness ?? Endianness::MSB) !== Endianness::LSB;
            $packed = [];

            foreach ($words as $word) {
                $r = ($word >> 24) & 0xFF;
                $g = ($word >> 16) & 0xFF;
                $b = ($word >> 8) & 0xFF;
                $packed[] = (($r & 0xF8) << 8) | (($g & 0xFC) << 3) | ($b >> 3);
            }

            return $this->packWordChunks($packed, $msb ? 'n*' : 'v*');
        }

        $temp = new PixelStore($width, $height, $spec, 1);
        $i = 0;

        for ($row = 0; $row < $height; $row++) {
            for ($col = 0; $col < $width; $col++) {
                // SDL words are sketch-draw colours (0xRRGGBBAA); PixelStore packs to host.
                $temp->setPixel($col, $row, $words[$i] ?? 0);
                $i++;
            }
        }

        return $temp->dump();
    }

    /**
     * @param  array<int, int>  $words
     */
    protected function packWordChunks(array $words, string $format): string
    {
        if ($words === []) {
            return '';
        }

        $bytes = '';
        $chunkSize = 512;

        for ($offset = 0, $count = count($words); $offset < $count; $offset += $chunkSize) {
            $chunk = array_slice($words, $offset, $chunkSize);
            $bytes .= pack($format, ...$chunk);
        }

        return $bytes;
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
     *
     * @deprecated Use {@see packRgbaWords()} — kept for any external callers.
     */
    protected function packWords(array $words, FormatSpec $spec): string
    {
        return $this->packRgbaWords($words, $this->width, $this->height, $spec);
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
