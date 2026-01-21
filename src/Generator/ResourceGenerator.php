<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator;

use CndApiMaker\Core\Definition\ResourceDefinition;

class ResourceGenerator
{
    public function __construct(
        private iterable $strategies
    ) {
    }

    public function generate(
        ResourceDefinition $def,
        string $targetFramework,
        string $basePath,
        bool $force,
        bool $dryRun,
        array $globalConfig = []
    ): GenerationResult {

        $ctx = GenerationContext::from($def, $targetFramework, $basePath, $force, $dryRun, $globalConfig);

        foreach ($this->strategies as $strategy) {
            if (!$strategy instanceof Strategy\ResourceGenerationStrategy) {
                continue;
            }

            if ($strategy->supports($ctx)) {
                return $strategy->generate($ctx);
            }
        }

        return new GenerationResult([]);
    }
}
