<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Definition\ResourceDefinition;

final class LaravelMigrationColumnsBuilder
{
    public function columns(ResourceDefinition $def): string
    {
        $lines = [];

        $uuidEnabled = (bool) ($def->api->uuid ?? true);

        $auditEnabled = (bool) ($def->features->audit ?? true);
        $tenant = (bool) ($def->features->tenant ?? true);
        $softDeletesEnabled = (bool) ($def->features->softDeletes ?? true);
        $idIsUUID = (bool) ($def->features->idIsInt ?? false);


        if ($uuidEnabled) {
            $lines[] = "            \$table->uuid('id')->primary();";
        } else {
            $lines[] = "            \$table->bigIncrements('id');";
        }

        foreach (($def->fields ?? []) as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if (in_array($f->name, ['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'], true)) {
                continue;
            }

            $lines[] = $this->columnLine($f, $uuidEnabled);
        }

        if ($auditEnabled) {
            $lines[] = $uuidEnabled && $idIsUUID
                ? "            \$table->uuid('created_by')->nullable()->index();"
                : "            \$table->unsignedBigInteger('created_by')->nullable()->index();";
            $lines[] = $uuidEnabled && $idIsUUID
                ? "            \$table->uuid('updated_by')->nullable()->index();"
                : "            \$table->unsignedBigInteger('updated_by')->nullable()->index();";
        }

        $lines[] = "            \$table->timestamps();";
        if($tenant){
        $lines[] = "            \$table->foreignUuid('tenant_id')->nullable()->constrained('tenants');";
        }
        if ($softDeletesEnabled) {
            $lines[] = "            \$table->softDeletes();";
        }

        return implode("\n", $lines) . "\n";
    }

    private function columnLine(FieldDefinition $f, bool $uuidEnabled): string
    {
        $name = (string) $f->name;

        $relationKind = strtolower((string) ($f->relationKind ?? ''));
        $isRelation = $relationKind !== '' || strtolower((string) ($f->type ?? '')) === 'relation';

        if ($isRelation) {
            return $this->relationColumnLine($f, $uuidEnabled);
        }

        $nullable = (bool) ($f->nullable ?? true);
        $type = strtolower((string) ($f->type ?? 'string'));
        $cast = strtolower((string) ($f->cast ?? ''));

        $method = match (true) {
            $type === 'uuid' => "\$table->uuid('{$name}')",
            $type === 'text' => "\$table->text('{$name}')",
            $type === 'bool' || $type === 'boolean' => "\$table->boolean('{$name}')",
            $type === 'int' || $type === 'integer' => "\$table->integer('{$name}')",
            $type === 'bigint' => "\$table->bigInteger('{$name}')",
            $type === 'float' || $type === 'double' => "\$table->float('{$name}')",
            $type === 'decimal' => "\$table->decimal('{$name}', 15, 2)",
            $type === 'date' || $cast === 'date' => "\$table->date('{$name}')",
            $type === 'datetime' || $type === 'timestamp' || $cast === 'datetime' || $cast === 'timestamp' => "\$table->dateTime('{$name}')",
            default => "\$table->string('{$name}')",
        };

        if ($nullable) {
            return "            {$method}->nullable();";
        }

        return "            {$method};";
    }

    private function relationColumnLine(FieldDefinition $f, bool $uuidEnabled): string
    {
        $relationKind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);
        $isOwningSide = (bool) ($f->isOwningSide ?? false);
        $nullable = (bool) ($f->nullable ?? true);

        if ($isCollection) {
            return "            ";
        }

        if ($relationKind === 'onetomany') {
            return "            ";
        }

        if (!$isOwningSide && ($relationKind === 'manytoone' || $relationKind === 'onetoone')) {
            return "            ";
        }

        $fk = $this->fkColumnName((string) $f->name);
        $targetTable = $this->targetTableName((string) ($f->targetEntity ?? ''));

        $base = $uuidEnabled
            ? "\$table->foreignUuid('{$fk}')"
            : "\$table->foreignId('{$fk}')";

        if ($nullable) {
            $base .= "->nullable()";
        }

        if ($targetTable !== null) {
            $base .= "->constrained('{$targetTable}')";
        }

        return "            {$base};";
    }

    private function fkColumnName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'id';
        }

        if (str_ends_with($name, '_id')) {
            return $name;
        }

        return $name . '_id';
    }

    private function targetTableName(string $targetEntity): ?string
    {
        $targetEntity = trim($targetEntity);
        if ($targetEntity === '') {
            return null;
        }

        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $targetEntity) ?? $targetEntity);

        if (str_ends_with($snake, 's')) {
            return $snake;
        }

        return $snake . 's';
    }
}
