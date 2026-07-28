<?php

namespace Microscrap\GFX\SDL3;

use Fabricate\Rendering\GFXRenderDriver;

class SDL3GFXRenderDriver extends GFXRenderDriver
{
    public function __construct()
    {
        parent::__construct(new SDL3Gfx);
    }
}
