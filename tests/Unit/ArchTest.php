<?php

arch('no debug statements leak into the package')
    ->expect('Microscrap\GFX\SDL3')
    ->not->toUse(['dd', 'dump', 'var_dump', 'ray', 'print_r']);

arch('the renderer extends the framework 2D surface')
    ->expect('Microscrap\GFX\SDL3\SDL3Gfx')
    ->toExtend('Fabricate\Rendering\Renderer2D');

arch('concerns are traits')
    ->expect('Microscrap\GFX\SDL3\Concerns')
    ->toBeTraits();
