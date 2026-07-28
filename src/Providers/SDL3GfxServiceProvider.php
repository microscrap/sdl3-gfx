<?php

namespace Microscrap\GFX\SDL3\Providers;

use Fabricate\Contracts\Chassis\BindingResolutionException;
use Fabricate\Contracts\Framebuffers\BufferFactory as FramebufferFactory;
use Fabricate\Framebuffers\FormatSpec;
use Fabricate\NutsAndBolts\ServiceProvider;
use Fabricate\Rendering\RenderManager;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use Microscrap\GFX\SDL3\SDL3GFXRenderDriver;

class SDL3GfxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->callAfterResolving('framebuffer', function (FramebufferFactory $framebuffers) {
            $framebuffers->extend(
                'sdl3',
                fn (int $width, int $height, FormatSpec $formatSpec) => new Sdl3Framebuffer(
                    $formatSpec,
                    $width,
                    $height,
                ),
            );
        });

        $this->callAfterResolving('gfx', function (RenderManager $renderers) {
            $renderers->extend('sdl3', fn () => new SDL3GFXRenderDriver);
        });
    }
}
