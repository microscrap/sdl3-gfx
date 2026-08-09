<?php

/**
 * Quarantined: render export proofs need SDL3Gfx + Fabricate Rendering.
 */
it('defers SDL3Gfx render export until Fabricate Rendering returns', function (): void {
    $this->markTestSkipped('SDL3Gfx render export deferred; Managed framebuffer covered in Unit/Sdl3FramebufferTest.');
});
