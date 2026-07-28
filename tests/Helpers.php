<?php

use Fabricate\Contracts\Framebuffers\Enums\BitDepth;
use Fabricate\Contracts\Framebuffers\Enums\Endianness;
use Fabricate\Contracts\Framebuffers\Enums\PixelFormat;
use Fabricate\Framebuffers\FormatSpec;
use Microscrap\GFX\SDL3\SDL3Gfx;

/**
 * A headless renderer with an RGBA8888 working spec, so test colors are
 * plain 0xRRGGBBAA words and read-backs can be asserted verbatim.
 */
function sdlGfx(int $width = 8, int $height = 8): SDL3Gfx
{
    return SDL3Gfx::headless($width, $height, sdlRgbaSpec());
}

function sdlRgbaSpec(): FormatSpec
{
    return new FormatSpec(PixelFormat::ROW_MAJOR, BitDepth::B32, endianness: Endianness::MSB);
}

/**
 * The SDL target as a flat row-major list of 0xRRGGBBAA words.
 *
 * @return array<int, int>
 */
function sdlWords(SDL3Gfx $renderer): array
{
    return $renderer->buffer()->readPixelWords();
}

/**
 * One RGBA word, addressed in physical (surface) coordinates.
 */
function sdlWord(SDL3Gfx $renderer, int $x, int $y): int
{
    return sdlWords($renderer)[($y * $renderer->buffer()->viewportWidth()) + $x];
}

/**
 * Count of pixels whose RGB channels differ from black (alpha ignored).
 */
function sdlPaintedCount(SDL3Gfx $renderer): int
{
    return count(array_filter(sdlWords($renderer), fn (int $word) => ($word >> 8) !== 0));
}
