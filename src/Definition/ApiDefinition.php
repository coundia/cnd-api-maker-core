<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

final class ApiDefinition
{
    public function __construct(
        public string $uriPrefix,
        public string $groupsRead,
        public string $groupsWrite,
        public bool $uuid = true
    ) {}
}
