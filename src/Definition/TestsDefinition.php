<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

final class TestsDefinition
{
    public function __construct(
        public bool $enabled = true,
        public string $framework = 'auto',
        public string $auth = 'none'
    ) {}
}
