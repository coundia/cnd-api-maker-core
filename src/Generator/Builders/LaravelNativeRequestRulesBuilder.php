<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class LaravelNativeRequestRulesBuilder
{
    public function __construct(
        private Naming $naming
    ) {
    }

    public function buildCreate(array $fields): string
    {
        return $this->build($fields, false);
    }

    public function buildUpdate(array $fields): string
    {
        return $this->build($fields, true);
    }

    private function build(array $fields, bool $isUpdate): string
    {
        $out = [];

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = trim((string) $f->name);
            if ($name === '' || $name === 'id' || $name === 'tenant_id' || $name === 'created_at' || $name === 'updated_at' || $name === 'deleted_at') {
                continue;
            }

            $key = $this->naming->snake($name);

            $rules = [];

            $nullable = (bool) ($f->nullable ?? false);
            if ($isUpdate) {
                $rules[] = 'sometimes';
                $rules[] = $nullable ? 'nullable' : 'required';
            } else {
                $rules[] = $nullable ? 'nullable' : 'required';
            }

            $rules = array_merge($rules, $this->typeRules($f));

            $max = (int) ($f->max ?? 0);
            if ($max > 0 && $this->supportsMaxRule($f)) {
                $rules[] = 'max:'.$max;
            }

            $min = (int) ($f->min ?? 0);
            if ($min > 0 && $this->supportsMinRule($f)) {
                $rules[] = 'min:'.$min;
            }

            if ($this->looksLikeEmail($name, $f)) {
                $rules[] = 'email';
            }

            $out[] = "            '".$key."' => ['".implode("', '", $rules)."'],";
        }

        if ($out === []) {
            return "            //\n";
        }

        return implode("\n", $out);
    }

    private function typeRules(FieldDefinition $f): array
    {
        $type = strtolower(trim((string) ($f->type ?? 'string')));
        $cast = strtolower(trim((string) ($f->cast ?? '')));

        $t = $cast !== '' ? $cast : $type;

        return match ($t) {
            'int', 'integer' => ['integer'],
            'float', 'double', 'decimal' => ['numeric'],
            'bool', 'boolean' => ['boolean'],
            'date' => ['date'],
            'datetime', 'timestamp' => ['date'],
            'array', 'json' => ['array'],
            default => ['string'],
        };
    }

    private function supportsMaxRule(FieldDefinition $f): bool
    {
        $type = strtolower(trim((string) ($f->type ?? 'string')));
        $cast = strtolower(trim((string) ($f->cast ?? '')));

        $t = $cast !== '' ? $cast : $type;

        return in_array($t, ['string', 'int', 'integer', 'float', 'double', 'decimal'], true);
    }

    private function supportsMinRule(FieldDefinition $f): bool
    {
        return $this->supportsMaxRule($f);
    }

    private function looksLikeEmail(string $name, FieldDefinition $f): bool
    {
        $n = strtolower($name);
        if ($n === 'email' || str_ends_with($n, '_email')) {
            return true;
        }

        $type = strtolower(trim((string) ($f->type ?? '')));
        return $type === 'email';
    }
}
