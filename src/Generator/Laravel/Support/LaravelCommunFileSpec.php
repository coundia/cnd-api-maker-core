<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel\Support;
final readonly class LaravelCommunFileSpec
{
    public function __construct(
        public string $kind,
        public string $stub,
        public string $path,
        public string $type
    ) {
    }
}
