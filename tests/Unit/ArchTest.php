<?php

arch('no debug statements leak into the package')
    ->expect('Microscrap\GFX\SDL3')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'print_r']);

arch('concerns are traits')
    ->expect('Microscrap\GFX\SDL3\Concerns')
    ->toBeTraits();

// SDL3Gfx → Fabricate\Rendering\Renderer2D is deferred until Rendering returns.
// Do not assert toExtend(Renderer2D) in this pass — loading SDL3Gfx fatals without fabricate/rendering.
