<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Definition\ResourceDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\LaravelCastResolver;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelModelGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer,
        private Naming $naming,
        private LaravelCastResolver $casts
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $stack = strtolower((string) ($ctx->def->app->stack ?? 'api_platform'));
        $tpl = $stack === 'native'
            ? $this->stubs->get('laravel/model.native')
            : $this->stubs->get('laravel/model');

        $table = $this->resolveTable($ctx);

        $idRequirement = $ctx->def->api->uuid ? '[0-9a-fA-F-]{36}' : '\d+';
        $traitHasUuids = $ctx->def->api->uuid ? "    use HasUuids;\n" : '';

        $usesSoftDeletes = $ctx->def->features->softDeletes ? "\nuse Illuminate\\Database\\Eloquent\\SoftDeletes;" : '';
        $traitSoftDeletes = $ctx->def->features->softDeletes ? "    use SoftDeletes;\n" : '';

        $usesTenantOwned = $ctx->def->features->tenant ? "\nuse App\\Models\\Concerns\\TenantOwned;" : '';
        $traitTenantOwned = $ctx->def->features->tenant ? "    use TenantOwned;\n" : '';

        $storage = $this->storageOrNull($ctx);

        $hasTenant = $ctx->def->features->enabled("tenant");

        $fillableItems = $storage?->fillable ?? [];
        $fillableItems = $this->augmentFillableWithRelationForeignKeys($ctx->def, $fillableItems);

        $fillable = $this->renderStringList($fillableItems);
        $hiddenList = $this->renderStringList($storage?->hidden ?? []);
        $castsList = $this->renderKeyValueList($storage?->casts ?? []);

        $hiddenBlock = $hiddenList !== ''
            ? "\n    protected \$hidden = [\n{$hiddenList}\n    ];\n"
            : '';

        $castsBlock = $castsList !== ''
            ? "\n    protected \$casts = [\n{$castsList}\n    ];\n"
            : '';

        $relations = $this->buildRelations($ctx->def, $ctx);
        $relationUses = $this->renderRelationUses($relations['uses']);
        $relationsBlock = $this->renderRelationsBlock($relations['methods']);

        $apiPropertiesBlock = $this->buildApiPropertiesBlock(
            $ctx->def,
            $ctx->groupsRead,
            $ctx->groupsWrite,
            $ctx->entitySnake
        );

        $opBase = $ctx->entitySnake;


        $content = $this->renderer->render($tpl, [
            'namespace' => 'App\\Models',
            'entity' => $ctx->entity,
            'table' => $table,
            'uriPrefix' => $ctx->uriPrefix,
            'groupsRead' => $ctx->groupsRead,
            'groupsWrite' => $ctx->groupsWrite,
            'opBase' => $opBase,
            'idRequirement' => $idRequirement,
            'traitHasUuids' => $traitHasUuids,
            'usesHasUuids' => "use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;",
            'usesSoftDeletes' => $usesSoftDeletes,
            'traitSoftDeletes' => $traitSoftDeletes,
            'usesTenantOwned' => $usesTenantOwned,
            'traitTenantOwned' => $traitTenantOwned,
            'fillable' => $fillable,
            'hiddenBlock' => $hiddenBlock,
            'castsBlock' => $castsBlock,
            'hasFiles' => $ctx->hasFiles(),
            'relationUses' => $relationUses,
            'relationsBlock' => $relationsBlock,
            'apiPropertiesBlock' => $apiPropertiesBlock,
            'hasTenant' => $hasTenant,
        ]);

        $path = $ctx->path('app/Models/' . $ctx->entity . '.php');
        $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

        return [
            ['path' => $path, 'type' => 'model'],
        ];
    }

    private function buildApiPropertiesBlock(
        ResourceDefinition $def,
        string $groupsRead,
        string $groupsWrite,
        string $entitySnake
    ): string {
        $lines = [];

        $lines[] = "#[ApiProperty(property: 'id', serialize: new Groups(['{$groupsRead}']))]";

        foreach (($def->fields ?? []) as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = trim((string) ($f->name ?? ''));
            if ($name === '' || $name === 'id') {
                continue;
            }

            if ($this->isRelationField($f)) {
                $kind = strtolower((string) ($f->relationKind ?? ''));
                $isCollection = (bool) ($f->isCollection ?? false);
                $isOwning = (bool) ($f->isOwningSide ?? false);

                $prop = $this->naming->camel($name);

                if ($isCollection || $kind === 'onetomany' || $kind === 'manytomany') {
                    $lines[] = "#[ApiProperty(property: '{$prop}', serialize: new Groups(['{$groupsRead}']))]";
                    continue;
                }

                if ($kind === 'manytoone' || ($kind === 'onetoone' && $isOwning)) {
                    $lines[] = "#[ApiProperty(property: '{$prop}', serialize: new Groups(['{$groupsRead}','{$groupsWrite}']))]";
                }

                continue;
            }

            $prop = $this->naming->camel($name);

            if ($name === 'tenant_id' || $name === $entitySnake.'_id') {
                $lines[] = "#[ApiProperty(property: '{$name}', serialize: new Groups(['{$groupsRead}','{$groupsWrite}']))]";
                continue;
            }

            $lines[] = "#[ApiProperty(property: '{$prop}', serialize: new Groups(['{$groupsRead}','{$groupsWrite}']))]";
        }

        if ((bool) ($def->features->tenant ?? false)) {
            $lines[] = "#[ApiProperty(property: 'tenantId', serialize: new Groups(['{$groupsRead}']))]";
        }

        if ((bool) ($def->features->audit ?? false)) {
            $lines[] = "#[ApiProperty(property: 'createdBy', serialize: new Groups(['{$groupsRead}']))]";
            $lines[] = "#[ApiProperty(property: 'updatedBy', serialize: new Groups(['{$groupsRead}']))]";
            $lines[] = "#[ApiProperty(property: 'createdAt', serialize: new Groups(['{$groupsRead}']))]";
            $lines[] = "#[ApiProperty(property: 'updatedAt', serialize: new Groups(['{$groupsRead}']))]";
        }

        if ((bool) ($def->features->softDeletes ?? false)) {
            $lines[] = "#[ApiProperty(property: 'deletedAt', serialize: new Groups(['{$groupsRead}']))]";
        }

        return "\n" . implode("\n", $lines) . "\n";
    }

    private function isRelationField(FieldDefinition $f): bool
    {
        $kind = trim((string) ($f->relationKind ?? ''));
        $type = strtolower((string) ($f->type ?? ''));
        return $kind !== '' || $type === 'relation';
    }

    private function resolveTable(GenerationContext $ctx): string
    {
        $storage = $this->storageOrNull($ctx);

        if ($storage !== null && property_exists($storage, 'table')) {
            $t = (string) ($storage->table ?? '');
            if ($t !== '') {
                return $t;
            }
        }

        return $this->naming->plural($ctx->entitySnake);
    }

    private function storageOrNull(GenerationContext $ctx): ?object
    {
        $def = $ctx->def;

        if (!is_object($def) || !property_exists($def, 'storage') || !is_object($def->storage)) {
            return null;
        }

        return $def->storage;
    }

    private function renderStringList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $out = [];
        foreach ($items as $it) {
            $out[] = "        '" . $this->naming->snake((string) $it) . "',";
        }

        return implode("\n", $out);
    }

    private function renderKeyValueList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $out = [];
        foreach ($items as $k => $v) {
            $key = (string) $k;
            $cast = $this->casts->cast(is_string($v) ? $v : null);

            if ($cast === null) {
                continue;
            }

            $out[] = "        '" . $key . "' => '" . $cast . "',";
        }

        return implode("\n", $out);
    }

    private function augmentFillableWithRelationForeignKeys(ResourceDefinition $def, array $fillable): array
    {
        $existing = [];
        foreach ($fillable as $f) {
            $existing[$this->naming->snake((string) $f)] = true;
        }

        foreach (($def->fields ?? []) as $field) {
            if (!$field instanceof FieldDefinition) {
                continue;
            }

            if (!$this->isRelationField($field)) {
                continue;
            }

            if ((bool) ($field->isCollection ?? false)) {
                continue;
            }

            $kind = strtolower((string) ($field->relationKind ?? ''));
            $isOwning = (bool) ($field->isOwningSide ?? false);

            if ($kind === 'manytoone' || ($kind === 'onetoone' && $isOwning)) {
                $fk = $this->fkColumnName((string) $field->name);
                if (!isset($existing[$fk])) {
                    $fillable[] = $fk;
                    $existing[$fk] = true;
                }
            }
        }

        return $fillable;
    }

    private function fkColumnName(string $name): string
    {
        $name = $this->naming->snake($name);

        if (str_ends_with($name, '_id')) {
            return $name;
        }

        return $name . '_id';
    }

    private function buildRelations(ResourceDefinition $def, GenerationContext $ctx): array
    {
        $uses = [];
        $methods = [];

        $hasTenant = $def->features->enabled("tenant");

        foreach (($def->fields ?? []) as $field) {
            if (!$field instanceof FieldDefinition) {
                continue;
            }

            if (!$this->isRelationField($field)) {
                continue;
            }

            $target = trim((string) ($field->targetEntity ?? ''));
            if ($target === '') {
                continue;
            }

            $name = $this->naming->camel((string) $field->name);
            $kind = strtolower((string) ($field->relationKind ?? ''));
            $isCollection = (bool) ($field->isCollection ?? false);
            $isOwning = (bool) ($field->isOwningSide ?? false);

            if ($isCollection) {
                if ($kind === 'onetomany') {
                    $uses['HasMany'] = 'Illuminate\\Database\\Eloquent\\Relations\\HasMany';
                    $foreignKey = $this->hasSideForeignKey($field, $ctx);
                    $methods[] =
                        "    public function {$name}(): HasMany\n" .
                        "    {\n" .
                        "        return \$this->hasMany({$target}::class, '{$foreignKey}');\n" .
                        "    }\n";
                }

                if ($kind === 'manytomany') {
                    $uses['BelongsToMany'] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany';
                    $pivot = $this->pivotTableName($ctx->entity, $target);
                    $methods[] =
                        "    public function {$name}(): BelongsToMany\n" .
                        "    {\n" .
                        "        return \$this->belongsToMany({$target}::class, '{$pivot}');\n" .
                        "    }\n";
                }

                continue;
            }

            if ($kind === 'manytoone') {
                $uses['BelongsTo'] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
                $fk = $this->fkColumnName((string) $field->name);
                $methods[] =
                    "    public function {$name}(): BelongsTo\n" .
                    "    {\n" .
                    "        return \$this->belongsTo({$target}::class, '{$fk}');\n" .
                    "    }\n";
                continue;
            }

            if ($kind === 'onetoone') {
                if ($isOwning) {
                    $uses['BelongsTo'] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
                    $fk = $this->fkColumnName((string) $field->name);
                    $methods[] =
                        "    public function {$name}(): BelongsTo\n" .
                        "    {\n" .
                        "        return \$this->belongsTo({$target}::class, '{$fk}');\n" .
                        "    }\n";
                } else {
                    $uses['HasOne'] = 'Illuminate\\Database\\Eloquent\\Relations\\HasOne';
                    $foreignKey = $this->hasSideForeignKey($field, $ctx);
                    $methods[] =
                        "    public function {$name}(): HasOne\n" .
                        "    {\n" .
                        "        return \$this->hasOne({$target}::class, '{$foreignKey}');\n" .
                        "    }\n";
                }

                continue;
            }
        }

        if($hasTenant){
            $uses['BelongsTo'] = 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo';
        }

        return [
            'uses' => array_values($uses),
            'methods' => $methods,
        ];
    }

    private function renderRelationUses(array $uses): string
    {
        if ($uses === []) {
            return '';
        }

        $lines = [];
        foreach ($uses as $u) {
            $lines[] = "use {$u};";
        }

        return "\n" . implode("\n", $lines);
    }

    private function renderRelationsBlock(array $methods): string
    {
        if ($methods === []) {
            return '';
        }

        return "\n" . implode("\n", $methods);
    }

    private function hasSideForeignKey(FieldDefinition $f, GenerationContext $ctx): string
    {
        $mappedBy = trim((string) ($f->mappedBy ?? ''));
        if ($mappedBy !== '') {
            return $this->fkColumnName($mappedBy);
        }

        return $this->fkColumnName($ctx->entitySnake);
    }

    private function pivotTableName(string $a, string $b): string
    {
        $sa = $this->naming->plural($this->naming->snake($a));
        $sb = $this->naming->plural($this->naming->snake($b));

        return strcmp($sa, $sb) <= 0 ? "{$sa}_{$sb}" : "{$sb}_{$sa}";
    }
}
