<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator;

final class GenerationResult
{
    /** @param array<int, array{path:string, type:string}> $files */
    public function __construct(public array $files) {}
}
