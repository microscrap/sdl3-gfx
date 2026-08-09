<?php

namespace Microscrap\GFX\SDL3\Providers;

use Fabricate\NutsAndBolts\ServiceProvider;
use Microscrap\GFX\SDL3\Sdl3Framebuffer;
use Microscrap\GFX\SDL3\SDL3WindowHandler;
use ScrapyardIO\Tubes\Contracts\Framebuffers\BufferFactory;
use ScrapyardIO\Tubes\Contracts\Windows\WindowFactory;
use ScrapyardIO\Tubes\Framebuffers\PendingFramebuffer;

class SDL3GfxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->container->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/framebuffers/sdl3.php' => $this->container->configPath('framebuffers/sdl3.php'),
            ], 'tubes-framebuffers-sdl3');

            $this->publishes([
                __DIR__.'/../../config/windows/sdl3.php' => $this->container->configPath('windows/sdl3.php'),
            ], 'tubes-windows-sdl3');
        }

        $this->callAfterResolving('framebuffer', function (BufferFactory $framebuffers): void {
            $framebuffers->extendDeferred(
                'sdl3',
                fn (PendingFramebuffer $pending) => Sdl3Framebuffer::sized(
                    $pending->widthValue(),
                    $pending->heightValue(),
                    $pending->hostFormatValue(),
                ),
            );
        });

        $this->callAfterResolving('window', function (WindowFactory $windows): void {
            $windows->extend('sdl3', SDL3WindowHandler::class);
        });
    }
}
