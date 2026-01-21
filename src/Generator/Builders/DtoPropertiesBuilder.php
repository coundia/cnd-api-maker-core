<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\FieldConstraints;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class DtoPropertiesBuilder
{
    public function __construct(
        private Naming $names,
        private FieldTypeResolver $types,
        private FieldConstraints $constraints
    ) {
    }

    public function input(array $fields, string $groupsWrite): string
    {
        $lines = [];

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;

            if ($name === 'id') {
                continue;
            }

            if ($this->isSystemField($name)) {
                continue;
            }

            if ($this->isRelation($f)) {
                $line = $this->inputRelationLine($f, $groupsWrite);
                if ($line !== null) {
                    $lines[] = $line;
                }
                continue;
            }

            $propName = $this->names->camel($name);
            $propType = $this->types->inputPhpType($f);
            $asserts = $this->constraints->symfonyAssertAttributes($f);

            $lines[] = implode("\n", array_merge(
                    $asserts,
                    [
                        "    #[Groups(['" . $groupsWrite . "'])]",
                        "    public " . $propType . " $" . $propName . " = null;",
                    ]
                )) . "\n";
        }

        return "\n" . implode("\n", $lines);
    }

    public function output(array $fields, string $groupsRead, ?GenerationContext $ctx = null): string
    {
        $lines = [];

        $seen = [
            'id' => true,
        ];

        $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public string \$id;\n";

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;

            if ($name === 'id') {
                continue;
            }

            if ($this->isSystemField($name)) {
                continue;
            }

            if ($this->isRelation($f)) {
                $rel = $this->outputRelationLine($f, $groupsRead, $seen);
                if ($rel !== null) {
                    $lines[] = $rel;
                }
                continue;
            }

            $propName = $this->names->camel($name);
            $seen[$propName] = true;

            $propType = $this->types->outputPhpType($f);

            $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public " . $propType . " $" . $propName . ";\n";
        }

        $tenantEnabled = (bool) ($ctx?->def->features->tenant ?? false);
        $auditEnabled = (bool) ($ctx?->def->features->audit ?? false);
        $softDeletesEnabled = (bool) ($ctx?->def->features->softDeletes ?? false);

        if ($tenantEnabled && !isset($seen['tenantId'])) {
            $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$tenantId;\n";
            $seen['tenantId'] = true;
        }

        if ($auditEnabled) {
            if (!isset($seen['createdBy'])) {
                $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$createdBy;\n";
                $seen['createdBy'] = true;
            }
            if (!isset($seen['updatedBy'])) {
                $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$updatedBy;\n";
                $seen['updatedBy'] = true;
            }
            if (!isset($seen['createdAt'])) {
                $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$createdAt;\n";
                $seen['createdAt'] = true;
            }
            if (!isset($seen['updatedAt'])) {
                $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$updatedAt;\n";
                $seen['updatedAt'] = true;
            }
        }

        if ($softDeletesEnabled && !isset($seen['deletedAt'])) {
            $lines[] = "    #[Groups(['" . $groupsRead . "'])]\n    public ?string \$deletedAt;\n";
            $seen['deletedAt'] = true;
        }

        return "\n" . implode("\n", $lines);
    }

    private function inputRelationLine(FieldDefinition $f, string $groupsWrite): ?string
    {
        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);

        if ($isCollection || $kind === 'onetomany' || $kind === 'manytomany') {
            return null;
        }

        $propName = $this->names->camel((string) $f->name) . 'Id';

        $asserts = $this->constraints->symfonyAssertAttributes($f);

        $nullable = (bool) ($f->nullable ?? true);
        $type = $nullable ? '?string' : 'string';

        $lines = array_merge(
            $asserts,
            [
                "    #[Groups(['" . $groupsWrite . "'])]",
                "    public " . $type . " $" . $propName . " = null;",
            ]
        );

        return implode("\n", $lines) . "\n";
    }

    private function outputRelationLine(FieldDefinition $f, string $groupsRead, array &$seen): ?string
    {
        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);

        if ($isCollection || $kind === 'onetomany' || $kind === 'manytomany') {
            $propName = $this->names->camel((string) $f->name) . 'Ids';
            if (isset($seen[$propName])) {
                return null;
            }
            $seen[$propName] = true;

            return "    #[Groups(['" . $groupsRead . "'])]\n    public array $" . $propName . " = [];\n";
        }

        $propName = $this->names->camel((string) $f->name) . 'Id';
        if (isset($seen[$propName])) {
            return null;
        }
        $seen[$propName] = true;

        return "    #[Groups(['" . $groupsRead . "'])]\n    public ?string $" . $propName . ";\n";
    }

    private function isRelation(FieldDefinition $f): bool
    {
        $kind = trim((string) ($f->relationKind ?? ''));
        $type = strtolower((string) ($f->type ?? ''));
        return $kind !== '' || $type === 'relation';
    }

    private function isSystemField(string $name): bool
    {
        return in_array($name, ['tenant_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'], true);
    }
}
