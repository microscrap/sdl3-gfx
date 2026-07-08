<?php

namespace Microscrap\GFX\SDL3\Concerns;

trait Sdl3Triangles
{
    public function drawTriangle(int $x0, int $y0, int $x1, int $y1, int $x2, int $y2, int $color): static
    {
        return $this->drawLine($x0, $y0, $x1, $y1, $color)
            ->drawLine($x1, $y1, $x2, $y2, $color)
            ->drawLine($x2, $y2, $x0, $y0, $color);
    }

    public function fillTriangle(int $x0, int $y0, int $x1, int $y1, int $x2, int $y2, int $color): static
    {
        // Early exit: Check if triangle is completely off-screen
        $minY = min($y0, $y1, $y2);
        $maxY = max($y0, $y1, $y2);
        if ($maxY < 0 || $minY >= $this->height()) {
            return $this;
        }

        // Sort vertices by Y coordinate (bubble sort for 3 elements)
        if ($y0 > $y1) {
            [$y0, $y1] = [$y1, $y0];
            [$x0, $x1] = [$x1, $x0];
        }

        if ($y1 > $y2) {
            [$y2, $y1] = [$y1, $y2];
            [$x2, $x1] = [$x1, $x2];
        }

        if ($y0 > $y1) {
            [$y0, $y1] = [$y1, $y0];
            [$x0, $x1] = [$x1, $x0];
        }

        // Degenerate case: flat triangle (all vertices at same Y)
        if ($y0 == $y2) {
            $a = $b = $x0;
            if ($x1 < $a) {
                $a = $x1;
            } elseif ($x1 > $b) {
                $b = $x1;
            }

            if ($x2 < $a) {
                $a = $x2;
            } elseif ($x2 > $b) {
                $b = $x2;
            }

            $width = $b - $a + 1;
            if ($width > 0) {
                $this->drawHorizontalLine($a, $y0, $width, $color);
            }
        } else {
            $dx01 = $x1 - $x0;
            $dy01 = $y1 - $y0;
            $dx02 = $x2 - $x0;
            $dy02 = $y2 - $y0;
            $dx12 = $x2 - $x1;
            $dy12 = $y2 - $y1;
            $sa = $sb = 0;

            if ($y1 == $y2) {
                $last = $y1;
            } // Include y1 scanline
            else {
                $last = $y1 - 1;
            } // Skip it

            // Upper half of triangle (from y0 to y1)
            for ($y = $y0; $y <= $last; $y++) {
                $a = (int) ($x0 + $sa / $dy01);
                $b = (int) ($x0 + $sb / $dy02);
                $sa += $dx01;
                $sb += $dx02;

                if ($a > $b) {
                    [$a, $b] = [$b, $a];
                }

                $width = $b - $a + 1;
                if ($width > 0) {
                    $this->drawHorizontalLine($a, $y, $width, $color);
                }
            }

            // Lower half of triangle (from y1 to y2)
            // This loop is skipped if y1=y2
            $sa = $dx12 * ($y - $y1);
            $sb = $dx02 * ($y - $y0);
            for (; $y <= $y2; $y++) {
                $a = (int) ($x1 + $sa / $dy12);
                $b = (int) ($x0 + $sb / $dy02);
                $sa += $dx12;
                $sb += $dx02;

                if ($a > $b) {
                    [$a, $b] = [$b, $a];
                }

                $width = $b - $a + 1;
                if ($width > 0) {
                    $this->drawHorizontalLine($a, $y, $width, $color);
                }
            }
        }

        return $this;
    }
}
