<?php

/**
 * Quarantined: SDL3Gfx + SDL soft-surface framebuffer tests require Fabricate Rendering
 * and the pre-0.7 Sdl3Framebuffer surface path. See Sdl3FramebufferTest for Managed coverage.
 */
it('defers SDL3Gfx suite until Fabricate Rendering returns', function (): void {
    $this->markTestSkipped('SDL3Gfx / Renderer2D port deferred; use Sdl3FramebufferTest.');
});
