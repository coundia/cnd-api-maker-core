<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Strategy;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\GenerationResult;

interface ResourceGenerationStrategy
{
    public function supports(GenerationContext $ctx): bool;

    public function generate(GenerationContext $ctx): GenerationResult;
}
