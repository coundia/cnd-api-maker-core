<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelFactoryGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer,
        private Naming $naming
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $tpl = $this->stubs->get('laravel/factory');

        $needsTenantImport = (bool) ($ctx->def->features->tenant ?? false);
        $relationImports = $this->renderRelationModelImports($ctx);

        $tenantImportLine = $needsTenantImport ? "use App\\Models\\Tenant;\n" : '';
        $forTenantMethod = $needsTenantImport ? $this->forTenantMethod() : '';

        $content = $this->renderer->render($tpl, [
            'entity' => $ctx->entity,
            'tenantImportLine' => $tenantImportLine,
            'relationImports' => $relationImports,
            'idLine' => $ctx->def->api->uuid ? "'id' => (string) Str::uuid()," : '',
            'tenantLine' => $ctx->def->features->tenant ? "'tenant_id' => Tenant::factory()," : '',
            'fieldsLines' => $this->buildFieldsLines($ctx),
            'forTenantMethod' => $forTenantMethod,
        ]);

        $path = $ctx->path('database/factories/' . $ctx->entity . 'Factory.php');
        $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

        return [
            ['path' => $path, 'type' => 'factory'],
        ];
    }

    private function buildFieldsLines(GenerationContext $ctx): string
    {
        $lines = [];

        foreach ($ctx->def->fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            $name = (string) $f->name;

            if ($name === '' || $name === 'id' || $name === 'tenant_id') {
                continue;
            }

            if ($this->isRelation($f)) {
                $relLine = $this->relationFieldLine($ctx, $f);
                if ($relLine !== null) {
                    $lines[] = $relLine;
                }
                continue;
            }

            $type = strtolower((string) $f->type);
            $cast = strtolower((string) ($f->cast ?? ''));

            if ($type === 'datetime' || $cast === 'datetime' || $type === 'timestamp' || $cast === 'timestamp') {
                $lines[] = "            '" . $name . "' => \$this->faker->dateTime(),";
                continue;
            }

            if ($type === 'date' || $cast === 'date') {
                $lines[] = "            '" . $name . "' => \$this->faker->date(),";
                continue;
            }

            if (in_array($type, ['bool', 'boolean'], true) || in_array($cast, ['bool', 'boolean'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->boolean(),";
                continue;
            }

            if (in_array($type, ['int', 'integer'], true) || in_array($cast, ['int', 'integer'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->numberBetween(1, 100),";
                continue;
            }

            if (in_array($type, ['float', 'double', 'decimal'], true) || in_array($cast, ['float', 'double', 'decimal'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->randomFloat(2, 1, 1000),";
                continue;
            }

            if ($name === 'code') {
                $lines[] = "            'code' => strtoupper(Str::limit(Str::slug(\$this->faker->unique()->word(), '_'), 80, '')),";
                continue;
            }

            if (in_array($name, ['label', 'libelle'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->sentence(3),";
                continue;
            }

            if ($name === 'name') {
                $lines[] = "            'name' => \$this->faker->sentence(3),";
                continue;
            }

            if (in_array($name, ['description', 'details'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->optional()->paragraph(),";
                continue;
            }

            if (in_array($name, ['color', 'couleur'], true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->optional()->hexColor(),";
                continue;
            }

            if ((bool) ($f->nullable ?? true)) {
                $lines[] = "            '" . $name . "' => \$this->faker->optional()->sentence(2),";
                continue;
            }

            $lines[] = "            '" . $name . "' => \$this->faker->sentence(2),";
        }

        return $lines === [] ? '' : implode("\n", $lines);
    }

    private function relationFieldLine(GenerationContext $ctx, FieldDefinition $f): ?string
    {
        $kind = strtolower((string) ($f->relationKind ?? ''));
        $isCollection = (bool) ($f->isCollection ?? false);
        $isOwning = (bool) ($f->isOwningSide ?? false);

        if ($isCollection || $kind === 'onetomany' || $kind === 'manytomany') {
            return null;
        }

        if ($kind === 'manytoone' || ($kind === 'onetoone' && $isOwning)) {
            $target = trim((string) ($f->targetEntity ?? ''));
            if ($target === '') {
                return null;
            }

            $fk = $this->fkColumnName((string) $f->name);
            $nullable = (bool) ($f->nullable ?? true);

            $factory = $target . '::factory()';

            if ($ctx->def->features->tenant) {
                $factory = $factory . "->forTenant(Tenant::factory())";
            }

            if ($nullable) {
                return "            '" . $fk . "' => \$this->faker->optional()->passthrough(" . $factory . "),";
            }

            return "            '" . $fk . "' => " . $factory . ",";
        }

        return null;
    }

    private function isRelation(FieldDefinition $f): bool
    {
        $kind = trim((string) ($f->relationKind ?? ''));
        $type = strtolower((string) ($f->type ?? ''));
        return $kind !== '' || $type === 'relation';
    }

    private function fkColumnName(string $name): string
    {
        $name = $this->naming->snake($name);

        if (str_ends_with($name, '_id')) {
            return $name;
        }

        return $name . '_id';
    }

    private function renderRelationModelImports(GenerationContext $ctx): string
    {
        $imports = [];

        foreach ($ctx->def->fields as $f) {
            if (!$f instanceof FieldDefinition) {
                continue;
            }

            if (!$this->isRelation($f)) {
                continue;
            }

            $kind = strtolower((string) ($f->relationKind ?? ''));
            $isCollection = (bool) ($f->isCollection ?? false);
            $isOwning = (bool) ($f->isOwningSide ?? false);

            if ($isCollection || $kind === 'onetomany' || $kind === 'manytomany') {
                continue;
            }

            if (!($kind === 'manytoone' || ($kind === 'onetoone' && $isOwning))) {
                continue;
            }

            $target = trim((string) ($f->targetEntity ?? ''));
            if ($target === '' || $target === $ctx->entity) {
                continue;
            }

            $imports[$target] = "use App\\Models\\{$target};";
        }

        if ($imports === []) {
            return '';
        }

        return implode("\n", array_values($imports)) . "\n";
    }

    private function forTenantMethod(): string
    {
        return "
        public function forTenant(Tenant|Factory \$tenant): self
        {
            return \$this->state(fn (): array => [
                'tenant_id' => \$tenant instanceof Tenant ? (string) \$tenant->getKey() : \$tenant,
            ]);
        }
        ";
    }
}
