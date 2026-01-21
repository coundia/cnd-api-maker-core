<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

final readonly class OpenApiSchemaVarsBuilder
{
    public function itemPropertiesLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $f) {
            $name = (string) ($f->name ?? '');
            if ($name === '') {
                continue;
            }

            [$type, $format] = $this->mapType((string) ($f->type ?? 'string'));

            $example = $f->example ?? null;
            if ($example === null) {
                $example = $this->defaultExample($type, $format, $name);
            }

            $props = ['type' => $type];
            if ($format !== null) {
                $props['format'] = $format;
            }
            if ($example !== null) {
                $props['example'] = $example;
            }

            $lines[] = "            '".$name."' => new \\ArrayObject(".$this->exportArray($props)."),\n";
        }

        return implode('', $lines);
    }

    public function requiredLines(array $fields): string
    {
        $req = [];

        foreach ($fields as $f) {
            $name = (string) ($f->name ?? '');
            if ($name === '') {
                continue;
            }

            $required = (bool) ($f->required ?? false);
            $nullable = (bool) ($f->nullable ?? false);

            if ($required && !$nullable) {
                $req[] = $name;
            }
        }

        if ($req === []) {
            return '';
        }

        return "        \$schema['required'] = ".$this->exportArray($req).";\n";
    }

    private function mapType(string $t): array
    {
        $t = strtolower(trim($t));

        return match ($t) {
            'int', 'integer', 'long' => ['integer', null],
            'float', 'double', 'decimal' => ['number', null],
            'bool', 'boolean' => ['boolean', null],
            'date' => ['string', 'date'],
            'datetime', 'date_time' => ['string', 'date-time'],
            'uuid' => ['string', 'uuid'],
            'email' => ['string', 'email'],
            default => ['string', null],
        };
    }

    private function defaultExample(string $type, ?string $format, string $name): mixed
    {
        if ($format === 'email') {
            return 'john@doe.test';
        }
        if ($format === 'uuid') {
            return 'c39017cf-9ce9-4754-be4e-64b422f3a48a';
        }
        if ($format === 'date-time') {
            return '2026-01-17T12:00:00+00:00';
        }
        if ($type === 'integer') {
            return 1;
        }
        if ($type === 'number') {
            return 10.5;
        }
        if ($type === 'boolean') {
            return true;
        }

        if (str_contains($name, 'first')) {
            return 'John';
        }
        if (str_contains($name, 'last')) {
            return 'Doe';
        }

        return 'value';
    }

    private function exportArray(array $a): string
    {
        return var_export($a, true);
    }
}
