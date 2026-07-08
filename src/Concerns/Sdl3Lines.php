<?php

namespace Microscrap\GFX\SDL3\Concerns;

trait Sdl3Lines
{
    public function drawLine(int $x0, int $y0, int $x1, int $y1, int $color): static
    {
        if ($x0 == $x1) {
            if ($y0 > $y1) {
                [$y0, $y1] = [$y1, $y0];
            }

            return $this->drawVerticalLine($x0, $y0, ($y1 - $y0) + 1, $color);
        } elseif ($y0 == $y1) {
            if ($x0 > $x1) {
                [$x0, $x1] = [$x1, $x0];
            }

            return $this->drawHorizontalLine($x0, $y0, ($x1 - $x0) + 1, $color);
        }

        return $this->drawArbitraryLine($x0, $y0, $x1, $y1, $color);
    }

    /**
     * Diagonal lines go to SDL's native rasterizer: clip in logical space,
     * rotate the endpoints into physical space, and let SDLRenderLine walk
     * the span instead of a per-pixel Bresenham loop.
     */
    public function drawArbitraryLine(int $x0, int $y0, int $x1, int $y1, int $color): static
    {
        $clipped = $this->clipLine($x0, $y0, $x1, $y1);

        if (is_null($clipped)) {
            // Line is completely outside viewport
            return $this;
        }

        [$x0, $y0, $x1, $y1] = $clipped;

        [$x0, $y0] = $this->applyRotation($x0, $y0);
        [$x1, $y1] = $this->applyRotation($x1, $y1);

        $this->buffer->line($x0, $y0, $x1, $y1, $color);

        return $this;
    }

    /**
     * Cohen-Sutherland line clipping algorithm
     * Clips line to viewport bounds [0, width) x [0, height)
     *
     * @return array|null Returns [x0, y0, x1, y1] if line is visible, null if completely clipped
     */
    protected function clipLine(int $x0, int $y0, int $x1, int $y1): ?array
    {
        $xMin = 0;
        $yMin = 0;
        $xMax = $this->width() - 1;
        $yMax = $this->height() - 1;

        $outcode0 = $this->computeOutcode($x0, $y0, $xMin, $yMin, $xMax, $yMax);
        $outcode1 = $this->computeOutcode($x1, $y1, $xMin, $yMin, $xMax, $yMax);

        while (true) {
            if (($outcode0 | $outcode1) === 0) {
                // Both points inside viewport - accept
                return [$x0, $y0, $x1, $y1];
            }

            if (($outcode0 & $outcode1) !== 0) {
                // Both points share an outside zone - reject
                return null;
            }

            // At least one point is outside - clip it
            $outcodeOut = $outcode0 !== 0 ? $outcode0 : $outcode1;

            if (($outcodeOut & 8) !== 0) { // Top
                $x = $x0 + ($x1 - $x0) * ($yMax - $y0) / ($y1 - $y0);
                $y = $yMax;
            } elseif (($outcodeOut & 4) !== 0) { // Bottom
                $x = $x0 + ($x1 - $x0) * ($yMin - $y0) / ($y1 - $y0);
                $y = $yMin;
            } elseif (($outcodeOut & 2) !== 0) { // Right
                $y = $y0 + ($y1 - $y0) * ($xMax - $x0) / ($x1 - $x0);
                $x = $xMax;
            } else { // Left (outcodeOut & 1)
                $y = $y0 + ($y1 - $y0) * ($xMin - $x0) / ($x1 - $x0);
                $x = $xMin;
            }

            if ($outcodeOut === $outcode0) {
                $x0 = (int) $x;
                $y0 = (int) $y;
                $outcode0 = $this->computeOutcode($x0, $y0, $xMin, $yMin, $xMax, $yMax);
            } else {
                $x1 = (int) $x;
                $y1 = (int) $y;
                $outcode1 = $this->computeOutcode($x1, $y1, $xMin, $yMin, $xMax, $yMax);
            }
        }
    }

    /**
     * Compute outcode for Cohen-Sutherland algorithm
     * Bits: LEFT=1, RIGHT=2, BOTTOM=4, TOP=8
     */
    protected function computeOutcode(int $x, int $y, int $xMin, int $yMin, int $xMax, int $yMax): int
    {
        $code = 0;
        if ($x < $xMin) {
            $code |= 1;
        }      // LEFT
        if ($x > $xMax) {
            $code |= 2;
        }      // RIGHT
        if ($y < $yMin) {
            $code |= 4;
        }      // BOTTOM
        if ($y > $yMax) {
            $code |= 8;
        }      // TOP

        return $code;
    }

    /**
     * Axis-aligned lines are 1-pixel-thick segments, so they ride the same
     * rotation-aware rect fast path as fillRect().
     */
    public function drawHorizontalLine(int $x, int $y, int $w, int $color): static
    {
        if ($w < 0) {
            $w *= -1;
            $x -= $w - 1;
        }

        return $this->drawSegment($x, $y, $w, 1, $color);
    }

    public function drawVerticalLine(int $x, int $y, int $h, int $color): static
    {
        if ($h < 0) {
            $h *= -1;
            $y -= $h - 1;
        }

        return $this->drawSegment($x, $y, 1, $h, $color);
    }
}
