<?php

namespace Microscrap\GFX\SDL3;

use Microscrap\Bindings\SDL3\DataObjects\SDLRenderer;
use Microscrap\Bindings\SDL3\DataObjects\SDLWindow;
use Microscrap\Bindings\SDL3\Enums\EventType;
use Microscrap\Bindings\SDL3\Enums\InitFlag;
use Microscrap\Bindings\SDL3\Error;
use Microscrap\Bindings\SDL3\Events;
use Microscrap\Bindings\SDL3\Init;
use Microscrap\Bindings\SDL3\Render;
use Microscrap\Bindings\SDL3\Video;
use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Windows\WindowException;
use ScrapyardIO\Tubes\Windows\WindowHandler;

/**
 * SDL3 OS window driver for tubes {@see \ScrapyardIO\Tubes\Canvas\OSWindow}.
 *
 * FormatSpec matches {@see Sdl3Framebuffer::rgbaSpec()}.
 * Present path: {@see Sdl3Framebuffer::present()} → SDL_RenderPresent (no PHP flush).
 */
class SDL3WindowHandler extends WindowHandler
{
    protected static bool $video_initialized = false;

    protected ?SDLWindow $window = null;

    protected ?SDLRenderer $renderer = null;

    protected bool $close_requested = false;

    protected ?SDL3InputHandler $input_handler = null;

    protected function defineFormatSpec(): FormatSpec
    {
        return Sdl3Framebuffer::rgbaSpec();
    }

    /**
     * Shared HumanInput companion — same instance for {@see \ScrapyardIO\Tubes\HumanInput\EngineInput}.
     */
    public function inputHandler(): SDL3InputHandler
    {
        return $this->input_handler ??= new SDL3InputHandler;
    }

    protected function bootNative(): void
    {
        if (! extension_loaded('sdl3')) {
            throw new WindowException('Required PHP extension [sdl3] is not loaded.');
        }

        if (! static::$video_initialized) {
            if (! Init::init(InitFlag::SDL_INIT_VIDEO)) {
                throw new WindowException('SDL_Init(SDL_INIT_VIDEO) failed: '.Error::getError());
            }
            static::$video_initialized = true;
        }

        $pair = Video::createWindowAndRenderer($this->title, $this->width, $this->height);
        $window = $pair['window'] ?? null;
        $renderer = $pair['renderer'] ?? null;

        if (is_null($window) || is_null($renderer)) {
            if (! is_null($renderer)) {
                Render::destroyRenderer($renderer);
            }
            if (! is_null($window)) {
                Video::destroyWindow($window);
            }

            throw new WindowException(
                "Could not create an SDL3 window+renderer ({$this->width}x{$this->height}): ".Error::getError()
            );
        }

        $this->window = $window;
        $this->renderer = $renderer;
        $this->close_requested = false;

        // Drop quit/destroy leftovers from prior windows in the same PHP process
        // (Pest opens several short-lived windows back-to-back).
        Events::flushEvents(EventType::SDL_EVENT_FIRST, EventType::SDL_EVENT_LAST);
    }

    protected function bindFramebuffer(): DeferredFramebuffer
    {
        if (is_null($this->renderer)) {
            throw new WindowException('SDL3WindowHandler has no SDLRenderer for bindFramebuffer().');
        }

        return Sdl3Framebuffer::attachedTo(
            $this->renderer,
            $this->formatSpec(),
            $this->width(),
            $this->height(),
        );
    }

    protected function presentNative(): void
    {
        $framebuffer = $this->framebuffer();
        if (! $framebuffer instanceof Sdl3Framebuffer) {
            throw new WindowException('SDL3WindowHandler expected an Sdl3Framebuffer.');
        }

        $framebuffer->present();
    }

    protected function pollNative(): void
    {
        $input = $this->inputHandler();

        while (! is_null($event = Events::pollEvent())) {
            $type = $event->eventType;
            if (
                $type === EventType::SDL_EVENT_QUIT->value
                || $type === EventType::SDL_EVENT_WINDOW_CLOSE_REQUESTED->value
            ) {
                $this->close_requested = true;
            }

            // Fan-out HumanInput before free; wheel readers free the native event.
            if (! $input->ingestEvent($event)) {
                Events::freeEvent($event);
            }
        }

        $input->poll();
    }

    public function shouldClose(): bool
    {
        return $this->close_requested || is_null($this->window);
    }

    /**
     * Drop the attached framebuffer before destroying SDL window/renderer.
     */
    public function close(): static
    {
        if (! $this->opened) {
            return $this;
        }

        $this->framebuffer = null;
        $this->destroyNative();
        $this->opened = false;

        return $this;
    }

    protected function destroyNative(): void
    {
        if (! is_null($this->input_handler)) {
            $this->input_handler->closeNative();
        }

        if (! is_null($this->renderer)) {
            Render::destroyRenderer($this->renderer);
            $this->renderer = null;
        }

        if (! is_null($this->window)) {
            Video::destroyWindow($this->window);
            $this->window = null;
        }

        $this->close_requested = false;
        // Do not SDL_Quit — headless Sdl3Framebuffer::sized() may still need SDL.
    }

    public function sdlWindow(): ?SDLWindow
    {
        return $this->window;
    }

    public function sdlRenderer(): ?SDLRenderer
    {
        return $this->renderer;
    }
}
