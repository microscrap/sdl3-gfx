<?php

namespace Microscrap\GFX\SDL3\Concerns;

trait Sdl3Rounds
{
    public function drawCircle(int $x0, int $y0, int $r, int $color): static
    {
        // Early exit if circle is completely off-screen
        if (($x0 + $r < 0) || ($x0 - $r >= $this->width()) ||
            ($y0 + $r < 0) || ($y0 - $r >= $this->height())) {
            return $this;
        }

        $f = 1 - $r;
        $ddf_x = 1;
        $ddf_y = -2 * $r;
        $x = 0;
        $y = $r;

        $this->drawPixel($x0, $y0 + $r, $color)
            ->drawPixel($x0, $y0 - $r, $color)
            ->drawPixel($x0 + $r, $y0, $color)
            ->drawPixel($x0 - $r, $y0, $color);

        while ($x < $y) {
            if ($f >= 0) {
                $y--;
                $ddf_y += 2;
                $f += $ddf_y;
            }
            $x++;
            $ddf_x += 2;
            $f += $ddf_x;

            $this->drawPixel($x0 + $x, $y0 + $y, $color)
                ->drawPixel($x0 - $x, $y0 + $y, $color)
                ->drawPixel($x0 + $x, $y0 - $y, $color)
                ->drawPixel($x0 - $x, $y0 - $y, $color)
                ->drawPixel($x0 + $y, $y0 + $x, $color)
                ->drawPixel($x0 - $y, $y0 + $x, $color)
                ->drawPixel($x0 + $y, $y0 - $x, $color)
                ->drawPixel($x0 - $y, $y0 - $x, $color);
        }

        return $this;
    }

    public function drawEllipse(int $x0, int $y0, int $rw, int $rh, int $color): static
    {
        // Early exit if ellipse is completely off-screen
        if (($x0 + $rw < 0) || ($x0 - $rw >= $this->width()) ||
            ($y0 + $rh < 0) || ($y0 - $rh >= $this->height())) {
            return $this;
        }

        $x = 0;
        $y = $rh;
        $rw2 = $rw * $rw;
        $rh2 = $rh * $rh;
        $twoRw2 = 2 * $rw2;
        $twoRh2 = 2 * $rh2;
        $decision = $rh2 - ($rw2 * $rh) + ($rw2 / 4);

        while (($twoRh2 * $x) < ($twoRw2 * $y)) {
            $this->drawPixel($x0 + $x, $y0 + $y, $color)
                ->drawPixel($x0 - $x, $y0 + $y, $color)
                ->drawPixel($x0 + $x, $y0 - $y, $color)
                ->drawPixel($x0 - $x, $y0 - $y, $color);
            $x++;
            if ($decision < 0) {
                $decision += $rh2 + ($twoRh2 * $x);
            } else {
                $decision += $rh2 + ($twoRh2 * $x) - ($twoRw2 * $y);
                $y--;
            }
        }

        $decision = (($rh2 * (2 * $x + 1) * (2 * $x + 1)) >> 2) +
            ($rw2 * ($y - 1) * ($y - 1)) - ($rw2 * $rh2);

        while ($y >= 0) {
            $this->drawPixel($x0 + $x, $y0 + $y, $color)
                ->drawPixel($x0 - $x, $y0 + $y, $color)
                ->drawPixel($x0 + $x, $y0 - $y, $color)
                ->drawPixel($x0 - $x, $y0 - $y, $color);
            $y--;
            if ($decision > 0) {
                $decision += $rw2 - ($twoRw2 * $y);
            } else {
                $decision += $rw2 + ($twoRh2 * $x) - ($twoRw2 * $y);
                $x++;
            }
        }

        return $this;
    }

    public function fillCircle(int $x0, int $y0, int $r, int $color): static
    {
        // Early exit if circle is completely off-screen
        if (($x0 + $r < 0) || ($x0 - $r >= $this->width()) ||
            ($y0 + $r < 0) || ($y0 - $r >= $this->height())) {
            return $this;
        }

        $this->drawVerticalLine($x0, $y0 - $r, 2 * $r + 1, $color);
        $this->fillCircleHelper($x0, $y0, $r, 3, 0, $color);

        return $this;
    }

    /**
     * Draw quarter-circle(s) for rounded rectangle corners
     *
     * Corner bit flags:
     * - 0x1 (1): Top-left quadrant
     * - 0x2 (2): Top-right quadrant
     * - 0x4 (4): Bottom-right quadrant
     * - 0x8 (8): Bottom-left quadrant
     *
     * Can be combined with bitwise OR (e.g., 0x1 | 0x2 = 3 for top corners)
     */
    protected function drawCircleHelper(int $x0, int $y0, int $r, int $corner_name, int $color): void
    {
        $f = 1 - $r;
        $ddf_x = 1;
        $ddf_y = -2 * $r;
        $x = 0;
        $y = $r;

        while ($x < $y) {
            if ($f >= 0) {
                $y--;
                $ddf_y += 2;
                $f += $ddf_y;
            }
            $x++;
            $ddf_x += 2;
            $f += $ddf_x;
            if ($corner_name & 0x4) { // Bottom-right
                $this->drawPixel($x0 + $x, $y0 + $y, $color);
                $this->drawPixel($x0 + $y, $y0 + $x, $color);
            }
            if ($corner_name & 0x2) { // Top-right
                $this->drawPixel($x0 + $x, $y0 - $y, $color);
                $this->drawPixel($x0 + $y, $y0 - $x, $color);
            }
            if ($corner_name & 0x8) { // Bottom-left
                $this->drawPixel($x0 - $y, $y0 + $x, $color);
                $this->drawPixel($x0 - $x, $y0 + $y, $color);
            }
            if ($corner_name & 0x1) { // Top-left
                $this->drawPixel($x0 - $y, $y0 - $x, $color);
                $this->drawPixel($x0 - $x, $y0 - $y, $color);
            }
        }
    }

    /**
     * Fill quarter-circle(s) for filled rounded rectangles
     *
     * Corner bit flags:
     * - 0x1 (1): Right side
     * - 0x2 (2): Left side
     *
     * Delta: Additional height to extend the fill (for rounded rect centers)
     */
    protected function fillCircleHelper(int $x0, int $y0, int $r, int $corners, int $delta, int $color): void
    {
        $f = 1 - $r;
        $ddf_x = 1;
        $ddf_y = -2 * $r;
        $x = 0;
        $y = $r;
        $px = $x;
        $py = $y;

        $delta++;

        while ($x < $y) {
            if ($f >= 0) {
                $y--;
                $ddf_y += 2;
                $f += $ddf_y;
            }
            $x++;
            $ddf_x += 2;
            $f += $ddf_x;
            // These checks avoid double-drawing certain lines, important
            // for displays with an INVERT drawing mode.
            if ($x < ($y + 1)) {
                if ($corners & 1) {
                    $this->drawVerticalLine($x0 + $x, $y0 - $y, 2 * $y + $delta, $color);
                }

                if ($corners & 2) {
                    $this->drawVerticalLine($x0 - $x, $y0 - $y, 2 * $y + $delta, $color);
                }

            }
            if ($y != $py) {
                if ($corners & 1) {
                    $this->drawVerticalLine($x0 + $py, $y0 - $px, 2 * $px + $delta, $color);
                }

                if ($corners & 2) {
                    $this->drawVerticalLine($x0 - $py, $y0 - $px, 2 * $px + $delta, $color);
                }

                $py = $y;
            }
            $px = $x;
        }
    }

    public function fillEllipse(int $x0, int $y0, int $rw, int $rh, int $color): static
    {
        // Early exit if ellipse is completely off-screen
        if (($x0 + $rw < 0) || ($x0 - $rw >= $this->width()) ||
            ($y0 + $rh < 0) || ($y0 - $rh >= $this->height())) {
            return $this;
        }

        $x = 0;
        $y = $rh;
        $rw2 = $rw * $rw;
        $rh2 = $rh * $rh;
        $twoRw2 = 2 * $rw2;
        $twoRh2 = 2 * $rh2;

        $decision = $rh2 - ($rw2 * $rh) + ($rw2 / 4);

        // region 1
        while (($twoRh2 * $x) < ($twoRw2 * $y)) {
            $x++;
            if ($decision < 0) {
                $decision += $rh2 + ($twoRh2 * $x);
            } else {
                $decision += $rh2 + ($twoRh2 * $x) - ($twoRw2 * $y);
                $this->drawHorizontalLine($x0 - ($x - 1), $y0 + $y, 2 * ($x - 1) + 1, $color);
                $this->drawHorizontalLine($x0 - ($x - 1), $y0 - $y, 2 * ($x - 1) + 1, $color);
                $y--;
            }
        }

        // region 2
        $decision = (($rh2 * (2 * $x + 1) * (2 * $x + 1)) >> 2) + ($rw2 * ($y - 1) * ($y - 1)) - ($rw2 * $rh2);
        while ($y >= 0) {
            $this->drawHorizontalLine($x0 - $x, $y0 + $y, 2 * $x + 1, $color);
            $this->drawHorizontalLine($x0 - $x, $y0 - $y, 2 * $x + 1, $color);

            $y--;
            if ($decision > 0) {
                $decision += $rw2 - ($twoRw2 * $y);
            } else {
                $decision += $rw2 + ($twoRh2 * $x) - ($twoRw2 * $y);
                $x++;
            }
        }

        return $this;
    }
}
