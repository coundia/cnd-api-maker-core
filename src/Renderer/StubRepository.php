<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Renderer;

class StubRepository
{
    public function __construct(private string $stubsDir) {}

    public function get(string $name): string
    {
        $path = rtrim($this->stubsDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.'.stub';
        if (!is_file($path)) {
            throw new \RuntimeException('Stub not found: '.$path);
        }
        return (string) file_get_contents($path);
    }
}
