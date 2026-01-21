<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Generator\Support\UniqueFieldPicker;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelTestsGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer,
        private UniqueFieldPicker $picker,
        private Naming $naming,
        private LaravelTestSupportGenerator $support
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $files = $this->support->generate($ctx);

        $tpl = $this->stubs->get('laravel/tests.api');

        $tenantEnabled =  $ctx->def->features->enabled("tenant");

        $snake = $this->naming->snake($ctx->entity);
        $snakePlural = $this->naming->pluralize($snake);

        [$uniqueField, $uniqueValueExpr] = $this->picker->pickUniqueField($ctx->def->fields);

        [$uniqueField, $uniqueValueExpr] = $this->pickSafeUniqueField(
            $ctx->def->fields,
            $ctx->entity,
            $uniqueField,
            $uniqueValueExpr
        );

        $table = $this->resolveTable($ctx, $snakePlural);

        $permissionPrefixUpper = strtoupper(str_replace(['-', ':'], '_', (string) $ctx->permissionPrefix));

        [$seedUpdateField, $assertUpdateField, $updatePayload] = $this->buildUpdateParts($ctx->def->fields, $uniqueField);
        $createPayload = $this->buildCreatePayload($ctx->def->fields, $ctx->entity, $uniqueField, $uniqueValueExpr, $tenantEnabled);
        $createResponseAssertions = $this->buildCreateResponseAssertions($ctx->def->fields, $uniqueField, $uniqueValueExpr);

        $deleteAssertions = $this->buildDeleteAssertions($ctx, $table, $tenantEnabled);

        [$fileTestsUse, $fileTests] = $this->buildFileTests($ctx, $table, $tenantEnabled, $uniqueField, $uniqueValueExpr);

        $content = $this->renderer->render($tpl, [
            'entity' => $ctx->entity,
            'entitySnake' => $snake,
            'entityUpper' => strtoupper($snake),
            'entitySnakePlural' => $snakePlural,
            'uriPrefix' => $ctx->uriPrefix,
            'table' => $table,
            'uniqueField' => $uniqueField,
            'uniqueValueExpr' => $uniqueValueExpr,
            'createPayload' => $createPayload,
            'createResponseAssertions' => $createResponseAssertions,
            'seedUpdateField' => $seedUpdateField,
            'updatePayload' => $updatePayload,
            'assertUpdateField' => $assertUpdateField,
            'permissionPrefix' => $ctx->permissionPrefix,
            'permissionPrefixUpper' => $permissionPrefixUpper,
            'deleteAssertions' => $deleteAssertions,

            'tenantUse' => $this->tenantUse($tenantEnabled),

            'createArrange' => $this->createArrange($ctx->entity, $tenantEnabled),
            'createWhereTenant' => $this->createWhereTenant($tenantEnabled),
            'createAssertTenant' => $this->createAssertTenant($tenantEnabled),

            'listArrangeAndSeed' => $this->listArrangeAndSeed($ctx->entity, $tenantEnabled),

            'itemArrangeAndSeed' => $this->itemArrangeAndSeed($ctx->entity, $tenantEnabled),

            'updateArrange' => $this->updateArrange($ctx->entity, $tenantEnabled),
            'updateTenantField' => $this->updateTenantField($tenantEnabled),
            'assertTenantLine' => $this->assertTenantLine($tenantEnabled),

            'deleteArrangeAndSeed' => $this->deleteArrangeAndSeed($ctx->entity, $tenantEnabled),
            'useRbac' => "use \\Tests\\Support\\GrantsRbacPermissions;",

            'fileTestsUse' => $fileTestsUse,
            'fileTests' => $fileTests,
            'hasTenant' => $tenantEnabled
        ]);

        $path = $ctx->path('tests/Feature/' . $ctx->entity . 'ApiTest.php');
        $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

        $files[] = ['path' => $path, 'type' => 'test'];

        return $files;
    }

    private function buildCreateResponseAssertions(array $fields, string $uniqueField, string $uniqueValueExpr): string
    {
        $lines = [];
        $lines[] = "        \$res->assertJson(fn (\\Illuminate\\Testing\\Fluent\\AssertableJson \$json) =>";
        $lines[] = "            \$json->whereType('id', 'string')";

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) ($f->name ?? '');
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                continue;
            }

            $key = $this->payloadKey($f);

            if ($this->isRelation($f)) {
                if (!$this->isOwningSingleRelation($f)) {
                    continue;
                }

                $lines[] = "                ->whereType('{$key}', 'string')";
                continue;
            }

            if ($name === $uniqueField) {
                $lines[] = "                ->where('{$key}', {$uniqueValueExpr})";
                continue;
            }

            $t = strtolower((string) ($f->type ?? ''));
            $cast = strtolower((string) ($f->cast ?? ''));

            if (in_array($t, ['int', 'integer'], true) || in_array($cast, ['int', 'integer'], true)) {
                $lines[] = "                ->whereType('{$key}', 'integer')";
                continue;
            }

            if (in_array($t, ['bool', 'boolean'], true) || in_array($cast, ['bool', 'boolean'], true)) {
                $lines[] = "                ->whereType('{$key}', 'boolean')";
                continue;
            }

            if (in_array($t, ['float', 'double', 'decimal'], true) || in_array($cast, ['float', 'double', 'decimal'], true)) {
                $lines[] = "                ->whereType('{$key}', 'double')";
                continue;
            }

            $lines[] = "                ->whereType('{$key}', 'string')";
        }

        $lines[] = "                ->etc()";
        $lines[] = "        );";

        return implode("\n", $lines);
    }

    private function buildFileTests(
        GenerationContext $ctx,
        string $table,
        bool $tenantEnabled,
        string $uniqueField,
        string $uniqueValueExpr
    ): array {
        $fileField = $this->firstFileField($ctx->def->fields ?? []);
        if ($fileField === null) {
            return ['', ''];
        }

        $permCreate = $ctx->permission('CREATE');
        $permView = $ctx->permission('VIEW');

        $createPayloadWithFile = $this->buildCreatePayloadWithFile(
            $ctx->def->fields ?? [],
            $ctx->entity,
            $uniqueField,
            $uniqueValueExpr,
            $fileField,
            $tenantEnabled
        );

        $headersInit = $tenantEnabled
            ? "        \$tenant = \$this->createTenant();\n"
            . "        \$this->setTenantContext(\$tenant);\n"
            . "        \$headers = \$this->tenantHeaders(\$tenant);\n"
            : "        \$headers = [];\n";

        $grant = $tenantEnabled
            ? "        \$this->grantPermissions(\$tenant, \$user, [\n"
            . "            '" . $permCreate . "',\n"
            . "            '" . $permView . "',\n"
            . "        ]);\n"
            : "        \$this->grantPermissions(null, \$user, [\n"
            . "            '" . $permCreate . "',\n"
            . "            '" . $permView . "',\n"
            . "        ]);\n";

        $method =
            "\n"
            . "    public function test_it_uploads_and_reads_" . $this->naming->snake($ctx->entity) . "_" . $fileField . "_binary(): void\n"
            . "    {\n"
            . "        Storage::disk('local')->deleteDirectory('uploads');\n\n"
            . "        \$user = User::factory()->create();\n"
            . "        Sanctum::actingAs(\$user);\n\n"
            . $headersInit . "\n"
            . $grant . "\n"
            . "        \$expected = 'hello-file';\n"
            . "        \$b64 = 'data:text/plain;base64,' . base64_encode(\$expected);\n\n"
            . "        \$uuid = Uuid::v4()->toString();\n\n"
            . "        \$res = \$this->apiLdPost('" . $ctx->uriPrefix . "', " . $createPayloadWithFile . ", \$headers);\n"
            . "        \$res->assertStatus(201);\n\n"
            . "        \$id = (string) DB::table('" . $table . "')\n"
            . "            ->where('" . $uniqueField . "', " . $uniqueValueExpr . ")\n"
            . "            ->value('id');\n\n"
            . "        \$this->assertNotSame('', \$id);\n\n"
            . "        \$path = (string) DB::table('" . $table . "')\n"
            . "            ->where('id', \$id)\n"
            . "            ->value('" . $fileField . "');\n\n"
            . "        \$this->assertNotSame('', \$path);\n"
            . "        \$this->assertTrue(Storage::disk('local')->exists(\$path));\n\n"
            . "        \$res2 = \$this->withHeaders(\$headers)->get('/api" . $ctx->uriPrefix . "/' . \$id . '/files');\n"
            . "        \$res2->assertOk();\n\n"
            . "    }\n";

        return [
            "use Illuminate\\Support\\Facades\\Storage;\n",
            $method,
        ];
    }

    private function firstFileField(array $fields): ?string
    {
        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) ($f->name ?? '');
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            $type = strtolower(trim((string) ($f->realType ?? $f->type ?? '')));
            if (in_array($type, ['blob', 'file'], true)) {
                return $name;
            }
        }

        return null;
    }

    private function buildCreatePayloadWithFile(
        array $fields,
        string $entity,
        string $uniqueField,
        string $uniqueValueExpr,
        string $fileField,
        bool $tenantEnabled
    ): string {
        $lines = [];
        $lines[] = '[';

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) ($f->name ?? '');
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            if ($name === $fileField) {
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                continue;
            }

            if ($this->isRelation($f)) {
                if (!$this->isOwningSingleRelation($f)) {
                    continue;
                }

                $key = $this->payloadKey($f);
                $idExpr = $this->relatedIdExpr($f, $tenantEnabled);
                if ($idExpr === null) {
                    continue;
                }

                $lines[] = "            '" . $key . "' => " . $idExpr . ",";
                continue;
            }

            $key = $this->payloadKey($f);

            if ($name === $uniqueField) {
                $lines[] = "            '" . $key . "' => " . $uniqueValueExpr . ",";
                continue;
            }

            $lines[] = "            '" . $key . "' => " . $this->sampleValueExpr($f, $entity) . ",";
        }

        $lines[] = "            '" . $fileField . "' => \$b64,";
        $lines[] = '        ]';

        return implode("\n", $lines);
    }

    private function tenantUse(bool $tenantEnabled): string
    {
        return "";//$tenantEnabled ? "use App\\Models\\Tenant;\n" : '';
    }

    private function createArrange(string $entity, bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return "  ";
        }

        return "";
    }

    private function createWhereTenant(bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return '';
        }

        return "            ->where('tenant_id', (string) \$tenant->getKey())\n";
    }

    private function createAssertTenant(bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return '';
        }

        return "            'tenant_id' => (string) \$tenant->getKey(),\n";
    }

    private function listArrangeAndSeed(string $entity, bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return "        {$entity}::factory()->count(2)->create();\n";
        }

        return "        {$entity}::factory()->count(2)->create([\n"
            . "            'tenant_id' => (string) \$tenant->getKey(),\n"
            . "        ]);\n";
    }

    private function itemArrangeAndSeed(string $entity, bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return "        "
                . "        \$p = {$entity}::factory()->create();\n";
        }

        return  "        \$p = {$entity}::factory()->create([\n"
            . "            'tenant_id' => (string) \$tenant->getKey(),\n"
            . "        ]);\n";
    }

    private function updateArrange(string $entity, bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return "  ";
        }

        return "";
    }

    private function updateTenantField(bool $tenantEnabled): string
    {
        return $tenantEnabled ? "            'tenant_id' => (string) \$tenant->getKey(),\n" : '';
    }

    private function assertTenantLine(bool $tenantEnabled): string
    {
        return $tenantEnabled ? "            'tenant_id' => (string) \$tenant->getKey(),\n" : '';
    }

    private function deleteArrangeAndSeed(string $entity, bool $tenantEnabled): string
    {
        if (!$tenantEnabled) {
            return "  "
                . "        \$p = {$entity}::factory()->create();\n";
        }

        return "        \$p = {$entity}::factory()->create([\n"
            . "            'tenant_id' => (string) \$tenant->getKey(),\n"
            . "        ]);\n";
    }

    private function buildDeleteAssertions(GenerationContext $ctx, string $table, bool $tenantEnabled): string
    {
        if ((bool) ($ctx->def->features->softDeletes ?? false)) {
            return "        \$deletedAt = DB::table('" . $table . "')\n"
                . "            ->where('id', (string) \$p->getKey())\n"
                . "            ->value('deleted_at');\n\n"
                . "        \$this->assertNotNull(\$deletedAt);\n";
        }

        if ($tenantEnabled) {
            return "        \$this->assertDatabaseMissing('" . $table . "', [\n"
                . "            'id' => (string) \$p->getKey(),\n"
                . "            'tenant_id' => (string) \$tenant->getKey(),\n"
                . "        ]);\n";
        }

        return "        \$this->assertDatabaseMissing('" . $table . "', [\n"
            . "            'id' => (string) \$p->getKey(),\n"
            . "        ]);\n";
    }

    private function buildCreatePayload(
        array $fields,
        string $entity,
        string $uniqueField,
        string $uniqueValueExpr,
        bool $tenantEnabled
    ): string {
        $lines = [];
        $lines[] = '[';


        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }


            if ($this->isRelation($f)) {

                if (!$this->isOwningSingleRelation($f)) {
                    continue;
                }


                $key = $this->payloadKey($f);
                $idExpr = $this->relatedIdExpr($f, $tenantEnabled);
                if ($idExpr === null) {
                    continue;
                }

                $lines[] = "            '" . $key . "' => " . $idExpr . ",";
                continue;
            }

            $key = $this->payloadKey($f);

            if ($name === $uniqueField) {
                $lines[] = "            '" . $key . "' => " . $uniqueValueExpr . ",";
                continue;
            }

            $lines[] = "            '" . $key . "' => " . $this->sampleValueExpr($f, $entity) . ",";
        }

        $lines[] = '        ]';

        return implode("\n", $lines);
    }

    private function buildUpdateParts(array $fields, string $uniqueField): array
    {
        $target = $this->pickUpdateField($fields, $uniqueField);

        if ($target === null) {
            return [
                "            'updated_at' => now(),",
                "            'updated_at' => now(),",
                "[\n            'updatedAt' => now()->toISOString(),\n        ]",
            ];
        }

        $name = (string) $target->name;

        $old = $this->sampleOldValueExpr($target);
        $new = $this->sampleNewValueExpr($target);

        $seedUpdateField = "            '" . $name . "' => " . $old . ",";
        $assertUpdateField = "            '" . $name . "' => " . $new . ",";

        $payloadKey = $this->naming->camel($name);
        $updatePayload = "[\n            '" . $payloadKey . "' => " . $new . ",\n        ]";

        return [$seedUpdateField, $assertUpdateField, $updatePayload];
    }

    private function pickUpdateField(array $fields, string $uniqueField): ?FieldDefinition
    {
        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if ($this->isRelation($f)) {
                continue;
            }

            if ($f->name === '' || $f->name === $uniqueField) {
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                continue;
            }

            if (in_array((string) $f->name, ['tenant_id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $t = strtolower((string) $f->type);
            if ($t === 'string' && in_array((string) $f->name, ['label', 'name', 'title', 'libelle'], true)) {
                return $f;
            }
        }

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if ($this->isRelation($f)) {
                continue;
            }

            if ($f->name === '' || $f->name === $uniqueField) {
                continue;
            }

            if (in_array((string) $f->name, ['tenant_id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            $t = strtolower((string) $f->type);
            if (in_array($t, ['string', 'bool', 'boolean', 'int', 'integer', 'float', 'double', 'decimal', 'datetime', 'date', 'timestamp'], true)) {
                return $f;
            }
        }

        return null;
    }

    private function sampleValueExpr(FieldDefinition $f, string $entity): string
    {
        $name = strtolower((string) $f->name);
        $type = strtolower((string) $f->type);
        $cast = strtolower((string) ($f->cast ?? ''));

        if (in_array($type, ['bool', 'boolean'], true) || in_array($cast, ['bool', 'boolean'], true)) {
            return 'true';
        }

        if (in_array($type, ['int', 'integer'], true) || in_array($cast, ['int', 'integer'], true)) {
            return '1';
        }

        if (in_array($type, ['float', 'double', 'decimal'], true) || in_array($cast, ['float', 'double', 'decimal'], true)) {
            return '1.5';
        }

        if ($type === 'datetime' || $cast === 'datetime' || $type === 'timestamp' || $cast === 'timestamp') {
            return "'2026-01-01T00:00:00+00:00'";
        }

        if ($type === 'date' || $cast === 'date') {
            return "'2026-01-01'";
        }

        if ($name === 'name' || $name === 'title') {
            return "'" . $entity . " ' . \$uuid";
        }

        if ($name === 'label' || $name === 'libelle') {
            return "'Label ' . \$uuid";
        }

        if ($name === 'color' || $name === 'couleur') {
            return "'#000000'";
        }

        if (str_ends_with($name, '_code') || $name === 'code') {
            return "'CODE_' . \$uuid";
        }

        return "'VAL-' . \$uuid";
    }

    private function sampleOldValueExpr(FieldDefinition $f): string
    {
        $type = strtolower((string) $f->type);
        $cast = strtolower((string) ($f->cast ?? ''));

        if (in_array($type, ['bool', 'boolean'], true) || in_array($cast, ['bool', 'boolean'], true)) {
            return 'true';
        }

        if (in_array($type, ['int', 'integer'], true) || in_array($cast, ['int', 'integer'], true)) {
            return '1';
        }

        if ($type === 'datetime' || $cast === 'datetime') {
            return "'2026-01-01T00:00:00+00:00'";
        }

        if ($type === 'date' || $cast === 'date') {
            return "'2026-01-01'";
        }

        return "'Old'";
    }

    private function sampleNewValueExpr(FieldDefinition $f): string
    {
        $name = strtolower((string) $f->name);
        $type = strtolower((string) $f->type);
        $cast = strtolower((string) ($f->cast ?? ''));

        if (in_array($type, ['bool', 'boolean'], true) || in_array($cast, ['bool', 'boolean'], true)) {
            return 'false';
        }

        if (in_array($type, ['int', 'integer'], true) || in_array($cast, ['int', 'integer'], true)) {
            return '2';
        }

        if ($type === 'datetime' || $cast === 'datetime') {
            return "'2026-02-01T00:00:00+00:00'";
        }

        if ($type === 'date' || $cast === 'date') {
            return "'2026-02-01'";
        }

        if ($name === 'label' || $name === 'libelle') {
            return "'Updated ' . \$uuid";
        }

        if ($name === 'name' || $name === 'title') {
            return "'Updated ' . \$uuid";
        }

        if ($name === 'color' || $name === 'couleur') {
            return "'#111111'";
        }

        if (str_ends_with($name, '_code') || $name === 'code') {
            return "'CODE_UPDATED_' . \$uuid";
        }

        return "'Updated'";
    }

    private function resolveTable(GenerationContext $ctx, string $fallback): string
    {
        $storage = $ctx->def->storage;

        if (is_object($storage) && property_exists($storage, 'table')) {
            $t = (string) ($storage->table ?? '');
            if ($t !== '') {
                return $t;
            }
        }

        return $fallback;
    }

    private function isRelation(FieldDefinition $f): bool
    {
        $kind = trim((string) ($f->relationKind ?? ''));
        $type = strtolower((string) ($f->type ?? ''));
        return $kind !== '' || $type === 'relation';
    }

    private function isOwningSingleRelation(FieldDefinition $f): bool
    {
        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);
        $isOwning = (bool) ($f->isOwningSide ?? false);

        return $kind === 'manytoone' || ($kind === 'onetoone' && $isOwning);
    }

    private function payloadKey(FieldDefinition $f): string
    {
        $name = (string) $f->name;

        if ($this->isOwningSingleRelation($f)) {
            if (str_ends_with($name, '_id')) {
                $name = substr($name, 0, -3);
            }

            $base = $this->naming->camel($name);

            return str_ends_with($base, 'Id') ? $base : $base . 'Id';
        }

        return $this->naming->camel($name);
    }

    private function relatedIdExpr(FieldDefinition $f, bool $tenantEnabled): ?string
    {
        $target = trim((string) ($f->targetEntity ?? ''));
        if ($target === '') {
            return null;
        }

        $fqn = "\\App\\Models\\{$target}";

        if ($tenantEnabled) {
            return "(string) {$fqn}::factory()->create(['tenant_id' => (string) \$tenant->getKey()])->getKey()";
        }

        return "(string) {$fqn}::factory()->create()->getKey()";
    }

    private function pickSafeUniqueField(array $fields, string $entity, string $uniqueField, string $uniqueValueExpr): array
    {
        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if ((string) $f->name === $uniqueField) {
                if ($this->isRelation($f)) {
                    break;
                }
                return [$uniqueField, $uniqueValueExpr];
            }
        }

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            if ($this->isRelation($f)) {
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                continue;
            }

            $type = strtolower((string) $f->type);
            if (in_array($type, ['string', 'text'], true)) {
                return [$name, $this->sampleValueExpr($f, $entity)];
            }
        }

        foreach ($fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;
            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            if ($this->isRelation($f)) {
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                continue;
            }

            return [$name, $this->sampleValueExpr($f, $entity)];
        }

        return [$uniqueField, $uniqueValueExpr];
    }
}
