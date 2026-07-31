<?php

namespace Microscrap\GFX\SDL3\Concerns;

trait Sdl3Rects
{
    public function drawRect(int $x, int $y, int $w, int $h, int $color): static
    {
        $this->drawHorizontalLine($x, $y, $w, $color);
        $this->drawHorizontalLine($x, ($y + $h) - 1, $w, $color);
        $this->drawVerticalLine($x, $y, $h, $color);

        return $this->drawVerticalLine(($x + $w) - 1, $y, $h, $color);
    }

    public function drawRoundRect(int $x, int $y, int $w, int $h, int $r, int $color): static
    {
        $max_radius = (($w < $h) ? $w : $h) / 2;
        if ($r > $max_radius) {
            $r = $max_radius;
        }

        $this->drawHorizontalLine($x + $r, $y, $w - 2 * $r, $color);
        $this->drawHorizontalLine($x + $r, ($y + $h) - 1, $w - 2 * $r, $color);
        $this->drawVerticalLine($x, $y + $r, $h - 2 * $r, $color);
        $this->drawVerticalLine($x + $w - 1, $y + $r, $h - 2 * $r, $color);

        $this->drawCircleHelper($x + $r, $y + $r, $r, 1, $color);
        $this->drawCircleHelper($x + $w - $r - 1, $y + $r, $r, 2, $color);
        $this->drawCircleHelper($x + $w - $r - 1, $y + $h - $r - 1, $r, 4, $color);
        $this->drawCircleHelper($x + $r, $y + $h - $r - 1, $r, 8, $color);

        return $this;
    }

    public function fillRect(int $x, int $y, int $w, int $h, int $color): static
    {
        return $this->drawSegment($x, $y, $w, $h, $color);
    }

    public function fillRoundRect(int $x, int $y, int $w, int $h, int $r, int $color): static
    {
        $max_radius = (($w < $h) ? $w : $h) / 2;
        if ($r > $max_radius) {
            $r = $max_radius;
        }

        // Fill center rectangle
        $this->fillRect($x + $r, $y, $w - 2 * $r, $h, $color);

        // Fill both side rounded areas
        $this->fillCircleHelper($x + $w - $r - 1, $y + $r, $r, 1, $h - 2 * $r - 1, $color);
        $this->fillCircleHelper($x + $r, $y + $r, $r, 2, $h - 2 * $r - 1, $color);

        return $this;
    }

    public function fillScreen(int $color): static
    {
        return $this->fillRect(0, 0, $this->width(), $this->height(), $color);
    }

    /**
     * Alias for fillRect() for API compatibility
     *
     * @deprecated Use fillRect() instead
     */
    public function drawFillRect(int $x, int $y, int $w, int $h, int $color): static
    {
        return $this->fillRect($x, $y, $w, $h, $color);
    }

    /**
     * The workhorse rect fill: clip in logical space, rotate (90° quadrant
     * rotations keep rectangles rectangular), then one native SDL fill.
     */
    public function drawSegment(int $x, int $y, int $width, int $height, int $color): static
    {
        // Early bounds check - if the entire segment is out of bounds, skip
        if (($x >= $this->width()) || ($y >= $this->height()) ||
            ($x + $width <= 0) || ($y + $height <= 0) ||
            ($width <= 0) || ($height <= 0)) {
            return $this;
        }

        // Intersect the active clip in logical space, before rotation, so a
        // rejected fill never reaches the buffer.
        $segment = $this->clipSegment($x, $y, $width, $height);

        if (is_null($segment)) {
            return $this;
        }

        [$x, $y, $width, $height] = [$segment->x, $segment->y, $segment->width, $segment->height];

        // Clip to the logical viewport
        $left = max(0, $x);
        $top = max(0, $y);
        $right = min($x + $width, $this->width());
        $bottom = min($y + $height, $this->height());

        $clipped_width = $right - $left;
        $clipped_height = $bottom - $top;

        if (($clipped_width <= 0) || ($clipped_height <= 0)) {
            return $this;
        }

        if ($this->rotation === 0) {
            $this->buffer->fillRectRaw($left, $top, $clipped_width, $clipped_height, $color);

            return $this;
        }

        // Rotate the clipped corners into physical space; the bounding box of
        // a 90°-rotated rectangle is the rectangle itself.
        $corners = [
            [$left, $top],
            [$right - 1, $top],
            [$left, $bottom - 1],
            [$right - 1, $bottom - 1],
        ];

        $rotated_corners = [];
        foreach ($corners as [$cx, $cy]) {
            $rotated_corners[] = $this->applyRotation($cx, $cy);
        }

        [$min_x, $min_y, $max_x, $max_y] = $this->getBoundingBox($rotated_corners);

        $this->buffer->fillRectRaw(
            (int) $min_x,
            (int) $min_y,
            (int) ($max_x - $min_x + 1),
            (int) ($max_y - $min_y + 1),
            $color
        );

        return $this;
    }
}
