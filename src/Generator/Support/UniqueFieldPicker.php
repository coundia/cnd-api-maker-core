<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class UniqueFieldPicker
{
    public function pickUniqueField(array $fields): array
    {
        foreach ($fields as $f) {
            if ($f instanceof FieldDefinition && $f->name === 'code') {
                return ['code', '$uuid'];
            }
        }

        foreach ($fields as $f) {
            if ($f instanceof FieldDefinition && !$f->nullable) {
                $name = (string) $f->name;
                $expr = $this->valueExprFor($f, $name);
                return [$name, $expr];
            }
        }

        return ['id', '$uuid'];
    }

    private function valueExprFor(FieldDefinition $f, string $name): string
    {
        $t = strtolower((string) $f->type);

        if (in_array($t, ['bool', 'boolean'], true)) {
            return 'true';
        }

        if (in_array($t, ['int', 'integer'], true)) {
            return '123';
        }

        if (in_array($t, ['float', 'double', 'decimal'], true)) {
            return '12.3';
        }

        if ($this->isDateLike($f)) {
            return "'2026-01-01'";
        }

        return "'VAL-'.\$uuid";
    }

    private function isDateLike(FieldDefinition $f): bool
    {
        $t = strtolower((string) $f->type);
        $c = strtolower((string) $f->cast);

        return in_array($t, ['date', 'datetime', 'timestamp'], true) || in_array($c, ['date', 'datetime', 'timestamp'], true);
    }
}
