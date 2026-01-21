<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class LaravelNativeResourceVarsBuilder
{
    public function __construct(
        private Naming $naming
    ) {
    }

    public function vars(GenerationContext $ctx, string $modelFqn): array
    {
        return [
            'namespace' => 'App\\Http\\Resources',
            'entity' => $ctx->entity,
            'modelFqn' => $modelFqn,
            'resourceFields' => $this->resourceFields($ctx->def->fields ?? []),
        ];
    }

    private function resourceFields(array $fields): string
    {
        $out = [];

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = trim((string) $f->name);
            if ($name === '' || $name === 'tenant_id' || $name === 'deleted_at') {
                continue;
            }

            $key = $this->naming->snake($name);
            $prop = $this->naming->snake($name);

            $out[] = "            '".$key."' => ".$this->valueExpr($f, $prop).",";
        }

        if ($out === []) {
            $out[] = "            'id' => (string) \$p->getKey(),";
        }

        return implode("\n", $out);
    }

    private function valueExpr(FieldDefinition $f, string $prop): string
    {
        $type = strtolower(trim((string) ($f->type ?? 'string')));
        $nullable = (bool) ($f->nullable ?? false);

        $base = match ($type) {
            'int', 'integer' => "(int) \$p->{$prop}",
            'float', 'double', 'decimal' => "(float) \$p->{$prop}",
            'bool', 'boolean' => "(bool) \$p->{$prop}",
            'datetime', 'timestamp' => "\$p->{$prop} instanceof \\DateTimeInterface ? \$p->{$prop}->format(\\DateTimeInterface::ATOM) : (is_string(\$p->{$prop}) ? \$p->{$prop} : null)",
            'date' => "\$p->{$prop} instanceof \\DateTimeInterface ? \$p->{$prop}->format('Y-m-d') : (is_string(\$p->{$prop}) ? \$p->{$prop} : null)",
            'array', 'json' => "(array) (\$p->{$prop} ?? [])",
            default => "(string) \$p->{$prop}",
        };

        if (!$nullable) {
            if (in_array($type, ['datetime', 'timestamp', 'date'], true)) {
                return $base;
            }
            return $base;
        }

        if (in_array($type, ['datetime', 'timestamp', 'date'], true)) {
            return $base;
        }

        if (in_array($type, ['array', 'json'], true)) {
            return $base;
        }

        if (in_array($type, ['int', 'integer'], true)) {
            return "\$p->{$prop} === null ? null : (int) \$p->{$prop}";
        }

        if (in_array($type, ['float', 'double', 'decimal'], true)) {
            return "\$p->{$prop} === null ? null : (float) \$p->{$prop}";
        }

        if (in_array($type, ['bool', 'boolean'], true)) {
            return "\$p->{$prop} === null ? null : (bool) \$p->{$prop}";
        }

        return "\$p->{$prop} === null ? null : (string) \$p->{$prop}";
    }
}
