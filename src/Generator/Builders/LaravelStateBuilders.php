<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class LaravelStateBuilders
{
    public function __construct(
        private Naming $names,
        private FieldTypeResolver $types
    ) {
    }

    public function mapperLines(array $fields): string
    {
        $lines = [];
        $lines[] = "        \$o->id = (string) \$p->getKey();";

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $isRelation = $this->isRelation($f);
            $kind = strtolower((string) ($f->relationKind ?? ''));
            $isCollection = (bool) ($f->isCollection ?? false);
            $isOwning = (bool) ($f->isOwningSide ?? false);

            if ($isRelation) {
                if ($isCollection || in_array($kind, ['onetomany', 'manytomany'], true)) {
                    continue;
                }

                if (!($kind === 'manytoone' || ($kind === 'onetoone' && $isOwning))) {
                    continue;
                }

                $prop = $this->names->camel((string) $f->name) . 'Id';
                $col = $this->names->snake((string) $f->name) . '_id';

                $expr = $f->nullable
                    ? "(\$p->{$col} !== null ? (string) \$p->{$col} : null)"
                    : "(string) (\$p->{$col} ?? '')";

                $lines[] = "        \$o->{$prop} = {$expr};";
                continue;
            }

            $prop = $this->names->camel((string) $f->name);
            $col = (string) $f->name;

            if ($this->types->isDateLike($f)) {
                $expr = "\$p->{$col} instanceof \\DateTimeInterface ? \$p->{$col}->format(\\DateTimeInterface::ATOM) : (\$p->{$col} !== null ? (string) \$p->{$col} : null)";
                $lines[] = "        \$o->{$prop} = {$expr};";
                continue;
            }

            $t = strtolower((string) $f->type);

            if (in_array($t, ['bool', 'boolean'], true)) {
                $expr = $f->nullable
                    ? "(\$p->{$col} !== null ? (bool) \$p->{$col} : null)"
                    : "(bool) (\$p->{$col} ?? false)";
                $lines[] = "        \$o->{$prop} = {$expr};";
                continue;
            }

            if (in_array($t, ['int', 'integer', 'long', 'bigint', 'duration'], true)) {
                $expr = $f->nullable
                    ? "(\$p->{$col} !== null ? (int) \$p->{$col} : null)"
                    : "(int) (\$p->{$col} ?? 0)";
                $lines[] = "        \$o->{$prop} = {$expr};";
                continue;
            }

            if (in_array($t, ['float', 'double', 'decimal', 'bigdecimal'], true)) {
                $expr = $f->nullable
                    ? "(\$p->{$col} !== null ? (float) \$p->{$col} : null)"
                    : "(float) (\$p->{$col} ?? 0)";
                $lines[] = "        \$o->{$prop} = {$expr};";
                continue;
            }

            if ($f->nullable) {
                $lines[] = "        \$o->{$prop} = \$p->{$col} !== null ? (string) \$p->{$col} : null;";
            } else {
                $lines[] = "        \$o->{$prop} = (string) (\$p->{$col} ?? '');";
            }
        }

        return "\n" . implode("\n", $lines) . "\n";
    }


    public function applyLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if ($this->isCollectionRelation($f)) {
                continue;
            }

            if ($this->isOwningSingleRelation($f)) {
                $camelId = $this->relationDtoProp($f);
                $col = $this->relationIdColumn($f);

                if ($f->nullable) {
                    $lines[] = "        if (array_key_exists('".$camelId."', \$payload)) {";
                    $lines[] = "            \$p->{$col} = \$payload['".$camelId."'] !== null ? (string) \$payload['".$camelId."'] : null;";
                    $lines[] = "        }";
                } else {
                    $lines[] = "        if (array_key_exists('".$camelId."', \$payload) && \$payload['".$camelId."'] !== null) {";
                    $lines[] = "            \$p->{$col} = (string) \$payload['".$camelId."'];";
                    $lines[] = "        }";
                }
                $lines[] = "";
                continue;
            }

            $camel = $this->names->camel((string) $f->name);
            $col = (string) $f->name;
            $t = strtolower((string) $f->type);

            if ($this->types->isDateLike($f)) {
                $lines[] = "        if (array_key_exists('".$camel."', \$payload)) {";
                $lines[] = "            \$p->{$col} = \$this->repo->parseDateOrNull(\$payload['".$camel."']);";
                $lines[] = "        }";
                $lines[] = "";
                continue;
            }

            if (in_array($t, ['bool', 'boolean'], true)) {
                if ($f->nullable) {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload)) {";
                    $lines[] = "            \$p->{$col} = \$payload['".$camel."'] === null ? null : (bool) \$payload['".$camel."'];";
                    $lines[] = "        }";
                } else {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload) && \$payload['".$camel."'] !== null) {";
                    $lines[] = "            \$p->{$col} = (bool) \$payload['".$camel."'];";
                    $lines[] = "        }";
                }
                $lines[] = "";
                continue;
            }

            if (in_array($t, ['int', 'integer', 'long', 'bigint', 'duration'], true)) {
                if ($f->nullable) {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload)) {";
                    $lines[] = "            \$p->{$col} = \$payload['".$camel."'] === null ? null : (int) \$payload['".$camel."'];";
                    $lines[] = "        }";
                } else {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload) && \$payload['".$camel."'] !== null) {";
                    $lines[] = "            \$p->{$col} = (int) \$payload['".$camel."'];";
                    $lines[] = "        }";
                }
                $lines[] = "";
                continue;
            }

            if (in_array($t, ['float', 'double', 'decimal', 'bigdecimal'], true)) {
                if ($f->nullable) {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload)) {";
                    $lines[] = "            \$p->{$col} = \$payload['".$camel."'] === null ? null : (float) \$payload['".$camel."'];";
                    $lines[] = "        }";
                } else {
                    $lines[] = "        if (array_key_exists('".$camel."', \$payload) && \$payload['".$camel."'] !== null) {";
                    $lines[] = "            \$p->{$col} = (float) \$payload['".$camel."'];";
                    $lines[] = "        }";
                }
                $lines[] = "";
                continue;
            }

            if ($f->nullable) {
                $lines[] = "        if (array_key_exists('".$camel."', \$payload)) {";
                $lines[] = "            \$p->{$col} = \$payload['".$camel."'] !== null ? (string) \$payload['".$camel."'] : null;";
                $lines[] = "        }";
                $lines[] = "";
                continue;
            }

            $lines[] = "        if ((\$payload['".$camel."'] ?? null) !== null) {";
            $lines[] = "            \$p->{$col} = (string) \$payload['".$camel."'];";
            $lines[] = "        }";
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    public function payloadLines(array $fields): string
    {
        $lines = [];

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if ($this->isCollectionRelation($f)) {
                continue;
            }

            if ($this->isOwningSingleRelation($f)) {
                $camelId = $this->relationDtoProp($f);
                $snakeId = $this->relationIdColumn($f);

                $dtoProp = '$data->' . $camelId;
                $expr = $dtoProp . ' ?? $this->stringOrNull($attrs[\'' . $camelId . '\'] ?? $attrs[\'' . $snakeId . '\'] ?? null)';

                $lines[] = "        if (\$this->has({$dtoProp}, \$attrs, '{$camelId}', '{$snakeId}')) {";
                $lines[] = "            \$payload['{$camelId}'] = {$expr};";
                $lines[] = "        }";
                $lines[] = "";
                continue;
            }

            $camel = $this->names->camel((string) $f->name);
            $snake = $this->names->snake((string) $f->name);

            $dtoProp = '$data->' . $camel;

            $expr = in_array(strtolower((string) $f->type), ['bool', 'boolean'], true)
                ? $dtoProp . ' ?? $this->boolOrNull($attrs[\'' . $camel . '\'] ?? $attrs[\'' . $snake . '\'] ?? null)'
                : $dtoProp . ' ?? $this->stringOrNull($attrs[\'' . $camel . '\'] ?? $attrs[\'' . $snake . '\'] ?? null)';

            $lines[] = "        if (\$this->has({$dtoProp}, \$attrs, '{$camel}', '{$snake}')) {";
            $lines[] = "            \$payload['{$camel}'] = {$expr};";
            $lines[] = "        }";
            $lines[] = "";
        }

        return rtrim(implode("\n", $lines));
    }

    private function isRelation(FieldDefinition $f): bool
    {
        $kind = trim((string) ($f->relationKind ?? ''));
        $type = strtolower((string) ($f->type ?? ''));
        return $kind !== '' || $type === 'relation';
    }

    private function isCollectionRelation(FieldDefinition $f): bool
    {
        if (!$this->isRelation($f)) {
            return false;
        }

        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);

        return $isCollection || in_array($kind, ['onetomany', 'manytomany'], true);
    }

    private function isOwningSingleRelation(FieldDefinition $f): bool
    {
        if (!$this->isRelation($f)) {
            return false;
        }

        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);
        $isOwning = (bool) ($f->isOwningSide ?? false);

        if ($isCollection || in_array($kind, ['onetomany', 'manytomany'], true)) {
            return false;
        }

        return $kind === 'manytoone' || ($kind === 'onetoone' && $isOwning);
    }

    private function relationDtoProp(FieldDefinition $f): string
    {
        return $this->names->camel((string) $f->name) . 'Id';
    }

    private function relationIdColumn(FieldDefinition $f): string
    {
        return $this->names->snake((string) $f->name) . '_id';
    }

    public function requiredLines(array $fields): string
    {
        $hasCode = false;
        $hasName = false;

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }
            if ($f->name === 'code' && !$f->nullable) {
                $hasCode = true;
            }
            if ($f->name === 'name' && !$f->nullable) {
                $hasName = true;
            }
        }

        $checks = [];

        if ($hasCode) {
            $checks[] = "(\$payload['code'] ?? null) === null";
        }
        if ($hasName) {
            $checks[] = "(\$payload['name'] ?? null) === null";
        }

        if ($checks === []) {
            return '';
        }

        return implode("\n", [
            '        if (' . implode(' || ', $checks) . ') {',
            "            throw new BadRequestHttpException('code and name are required');",
            '        }',
            '',
        ]);
    }

    public function createDefaultsLines(array $fields): string
    {
        $hasStatus = false;
        $hasActive = false;

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }
            if ($f->name === 'status') {
                $hasStatus = true;
            }
            if ($f->name === 'active') {
                $hasActive = true;
            }
        }

        $lines = [];

        if ($hasStatus) {
            $lines[] = "        if (\$p->status === null) {";
            $lines[] = "            \$p->status = 'draft';";
            $lines[] = "        }";
        }

        if ($hasActive) {
            $lines[] = "        if (\$p->active === null) {";
            $lines[] = "            \$p->active = true;";
            $lines[] = "        }";
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    public function auditLines(bool $auditEnabled): string
    {
        if (!$auditEnabled) {
            return '';
        }

        return implode("\n", [
                '        if ($isCreate) {',
                '            $p->created_by = Auth::id() ? (string) Auth::id() : null;',
                '        }',
                '        $p->updated_by = Auth::id() ? (string) Auth::id() : null;',
            ]) . "\n";
    }
}
