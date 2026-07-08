<?php

namespace Microscrap\GFX\SDL3\Concerns;

trait Sdl3Bitmaps
{
    abstract public function drawPixel(int $x, int $y, int $color): static;

    public function drawBitmap(int $x, int $y, array $bitmap, int $w, int $h, int $color, ?int $bg = null): static
    {
        // Early exit if bitmap is completely off-screen
        if (($x >= $this->width()) || ($y >= $this->height()) ||
            (($x + $w) <= 0) || (($y + $h) <= 0)) {
            return $this;
        }

        $byte_width = ($w + 7) >> 3;

        // Optimization: Draw background as single rect if needed
        if (! is_null($bg)) {
            $this->fillRect($x, $y, $w, $h, $bg);
        }

        // Draw foreground pixels (MSB-first bit order)
        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $byte = max(0, min(255, $bitmap[$j * $byte_width + ($i >> 3)] ?? 0));
                $bit = 0x80 >> ($i & 7);

                if ($byte & $bit) {
                    $this->drawPixel($x + $i, $y + $j, $color);
                }
            }
        }

        return $this;
    }

    public function drawXBitmap(int $x, int $y, array $bitmap, int $w, int $h, int $color, ?int $bg = null): static
    {
        // Early exit if bitmap is completely off-screen
        if (($x >= $this->width()) || ($y >= $this->height()) ||
            (($x + $w) <= 0) || (($y + $h) <= 0)) {
            return $this;
        }

        $byte_width = ($w + 7) >> 3;

        // Optimization: Draw background as single rect if needed
        if (! is_null($bg)) {
            $this->fillRect($x, $y, $w, $h, $bg);
        }

        // Draw foreground pixels (LSB-first bit order)
        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $byte = max(0, min(255, $bitmap[$j * $byte_width + ($i >> 3)] ?? 0));
                $bit = 0x01 << ($i & 7);

                if ($byte & $bit) {
                    $this->drawPixel($x + $i, $y + $j, $color);
                }
            }
        }

        return $this;
    }

    /**
     * Draw grayscale bitmap
     *
     * @param  array  $bitmap  Array of grayscale values (0-255)
     */
    public function drawGrayscaleBitmap(int $x, int $y, array $bitmap, int $w, int $h): static
    {
        // Early exit if bitmap is completely off-screen
        if (($x >= $this->width()) || ($y >= $this->height()) ||
            (($x + $w) <= 0) || (($y + $h) <= 0)) {
            return $this;
        }

        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $gray = max(0, min(255, $bitmap[$j * $w + $i] ?? 0));
                $this->drawPixel($x + $i, $y + $j, $gray);
            }
        }

        return $this;
    }

    /**
     * Draw color map (array of color int values)
     *
     * @param  array  $map  Array of int color values (null = transparent)
     */
    public function drawColorMap(int $x, int $y, array $map, int $w, int $h): static
    {
        // Early exit if bitmap is completely off-screen
        if (($x >= $this->width()) || ($y >= $this->height()) ||
            (($x + $w) <= 0) || (($y + $h) <= 0)) {
            return $this;
        }

        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $color = $map[$j * $w + $i] ?? null;
                if (! is_null($color)) {
                    $this->drawPixel($x + $i, $y + $j, $color);
                }
            }
        }

        return $this;
    }
}
