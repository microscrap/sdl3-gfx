<?php

namespace Microscrap\GFX\SDL3;

use BareMetal\Contracts\GFX\RendererException;

class Sdl3GFXException extends RendererException
{
    public static function surfaceCreationFailed(int $width, int $height, string $sdl_error = ''): static
    {
        $detail = ($sdl_error === '') ? '' : " SDL says: {$sdl_error}";

        return new static("Could not create a {$width}x{$height} off-screen SDL surface.{$detail}");
    }

    public static function rendererCreationFailed(string $sdl_error = ''): static
    {
        $detail = ($sdl_error === '') ? '' : " SDL says: {$sdl_error}";

        return new static("Could not create a software SDL renderer for the off-screen surface.{$detail}");
    }

    public static function invalidProperty(string $name, string $class): static
    {
        return new static("Unknown property {$name} on {$class}.");
    }
}
