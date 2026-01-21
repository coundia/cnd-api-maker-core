<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Generator\Builders\LaravelNativeControllerVarsBuilder;
use CndApiMaker\Core\Generator\Builders\LaravelNativeRequestVarsBuilder;
use CndApiMaker\Core\Generator\Builders\LaravelNativeResourceVarsBuilder;
use CndApiMaker\Core\Generator\Builders\LaravelNativeRoutesVarsBuilder;
use CndApiMaker\Core\Generator\Builders\LaravelStateBuilders;
use CndApiMaker\Core\Generator\Builders\OpenApiSchemaVarsBuilder;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Laravel\Support\LaravelCommunContext;
use CndApiMaker\Core\Generator\Laravel\Support\LaravelCommunFiles;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelCommunGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer,
        private LaravelStateBuilders $b,
        private LaravelNativeControllerVarsBuilder $controllerVars,
        private LaravelNativeResourceVarsBuilder $resourceVars,
        private LaravelNativeRoutesVarsBuilder $routesVars,
        private LaravelNativeRequestVarsBuilder $requests,
        private OpenApiSchemaVarsBuilder $openApiVars,
        private Naming $naming
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $s = LaravelCommunContext::from($ctx);

        $files = [];

        foreach (LaravelCommunFiles::all($ctx, $s) as $f) {
            $tpl = $this->stubs->get($f->stub);
            $vars = $this->renderVarsFor($f->kind, $ctx, $s);

            $vars['namespace'] = $this->namespaceFromPath($ctx, $f->path);

            $content = $this->renderer->render($tpl, $vars);

            if ($f->kind === 'routes') {
                $this->writer->appendToLaravelRoutes(
                    $f->path,
                    (string) ($vars['useControllerLine'] ?? ''),
                    (string) ($vars['routeGroupBlock'] ?? ''),
                    (string) ($vars['routeNeedle'] ?? ''),
                    $ctx->dryRun
                );
                $files[] = ['path' => $f->path, 'type' => $f->type];
                continue;
            }

            $this->writer->write($f->path, $content, $ctx->force, $ctx->dryRun);
            $files[] = ['path' => $f->path, 'type' => $f->type];
        }

        return $files;
    }

    private function renderVarsFor(string $kind, GenerationContext $ctx, LaravelCommunContext $s): array
    {
        $securityEnabled = (bool) $ctx->def->features->enabled('security');
        $hasTenant = (bool) $ctx->def->features->enabled('tenant');
        $security = $this->securityVars($ctx, $securityEnabled);

        $entityPlural = $this->naming->plural($ctx->entity);

        $common = [
                'entity' => $ctx->entity,
                'modelFqn' => $s->modelFqn,
                'hasTenant' => $hasTenant,
            ] + $security;

        return match ($kind) {
            'controller' => $this->controllerVars->vars($ctx, $s, $security),
            'resource' => $this->resourceVars->vars($ctx, $s->modelFqn),
            'routes' => $this->routesVars->vars($ctx),

            'request_store' => $this->requests->storeVars($ctx),
            'request_update' => $this->requests->updateVars($ctx),

            'mapper' => $common + [
                    'dtoFqn' => $s->outputFqn,
                    'mapLines' => $this->b->mapperLines($ctx->def->fields),
                ],

            'repository' => $common + [
                    'tenantEnabled' => $ctx->def->features->enabled('tenant') ? '1' : '',
                    'softDeletesEnabled' => $ctx->def->features->enabled('softDeletes') ? '1' : '',
                ],

            'payload_resolver' => $common + [
                    'inputFqn' => $s->inputFqn,
                    'payloadLines' => $this->b->payloadLines($ctx->def->fields),
                ],

            'writer' => $common + [
                    'requireLines' => $this->b->requiredLines($ctx->def->fields),
                    'idLine' => $ctx->def->api->uuid ? "        \$p->id = (string) Str::uuid();\n" : '',
                    'createDefaultsLines' => $this->b->createDefaultsLines($ctx->def->fields),
                    'auditLines' => $this->b->auditLines((bool) $ctx->def->features->enabled('audit')),
                    'applyLines' => $this->b->applyLines($ctx->def->fields),
                    'opCreate' => $ctx->opBase . '_create',
                    'opUpdate' => $ctx->opBase . '_update',
                ] + $this->fileWriterVars($ctx),

            'collection_provider' => $common + [
                    'repoFqn' => $s->repoFqn,
                    'mapperFqn' => $s->mapperFqn,
                    'permissionPrefix' => $ctx->permissionPrefix,
                ],

            'item_provider' => $common + [
                    'repoFqn' => $s->repoFqn,
                    'mapperFqn' => $s->mapperFqn,
                    'permissionPrefix' => $ctx->permissionPrefix,
                ] + $this->fileReaderVars($ctx),

            'write_processor' => $common + [
                    'inputFqn' => $s->inputFqn,
                    'payloadResolverFqn' => $s->payloadResolverFqn,
                    'repoFqn' => $s->repoFqn,
                    'writerFqn' => $s->writerFqn,
                    'mapperFqn' => $s->mapperFqn,
                    'permissionPrefix' => $ctx->permissionPrefix,
                    'opCreate' => $ctx->opBase . '_create',
                    'opUpdate' => $ctx->opBase . '_update',
                ],

            'delete_processor' => $common + [
                    'repoFqn' => $s->repoFqn,
                    'permissionPrefix' => $ctx->permissionPrefix,
                    'opDelete' => $ctx->opBase . '_delete',
                ],

            'openapi_entity_schemas', 'openapi_entity_paths' => [
                    'entity' => $ctx->entity,
                    'routePrefix' => $ctx->uriPrefix,
                    'entityPluralLower' => strtolower($entityPlural),
                    'itemPropertiesLines' => $this->openApiVars->itemPropertiesLines($ctx->def->fields ?? []),
                    'createPropertiesLines' => $this->openApiVars->itemPropertiesLines($ctx->def->fields ?? []),
                    'updatePropertiesLines' => $this->openApiVars->itemPropertiesLines($ctx->def->fields ?? []),
                    'createRequiredLines' => $this->openApiVars->requiredLines($ctx->def->fields ?? []),
                ] + $security,

            default => $common,
        };
    }

    private function fileReaderVars(GenerationContext $ctx): array
    {
        $fields = is_array($ctx->def->fields ?? null) ? $ctx->def->fields : [];

        $fileFields = [];
        foreach ($fields as $f) {
            if (is_object($f)) {
                $name = (string) ($f->name ?? '');
                $type = strtolower(trim((string) ($f->realType ?? $f->type ?? '')));
            } elseif (is_array($f)) {
                $name = (string) ($f['name'] ?? '');
                $type = strtolower(trim((string) ($f['realType'] ?? $f['type'] ?? '')));
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }

            if (!in_array($type, ['blob', 'file'], true)) {
                continue;
            }

            $fileFields[] = $name;
        }

        if ($fileFields === []) {
            return [
                'fileReaderUses' => '',
                'fileReaderCtorArg' => '',
                'fileReaderCtorAssign' => '',
                'fileReadCasesLines' => '',
                'hasFiles' => '',
            ];
        }

        $uses = "use App\\Files\\FileReader;\n";
        $ctorArg = ",\n        private FileReader \$files";

        $lines = [];
        foreach ($fileFields as $field) {
            $op = $ctx->opBase . '_file';

            $lines[] =
                "        if (\$opName === '{$op}') {\n" .
                "            return \$this->files->streamFromModelPath((string) (\$p->{$field} ?? ''), '{$ctx->entitySnake}-{$field}');\n" .
                "        }\n\n";
        }

        return [
            'fileReaderUses' => $uses,
            'fileReaderCtorArg' => $ctorArg,
            'fileReaderCtorAssign' => '',
            'fileReadCasesLines' => implode('', $lines),
            'hasFiles' => '1',
        ];
    }

    private function fileWriterVars(GenerationContext $ctx): array
    {
        $fields = is_array($ctx->def->fields ?? null) ? $ctx->def->fields : [];

        $fileFields = [];
        foreach ($fields as $f) {
            if (is_object($f)) {
                $name = (string) ($f->name ?? '');
                $type = strtolower(trim((string) ($f->realType ?? $f->type ?? '')));
            } elseif (is_array($f)) {
                $name = (string) ($f['name'] ?? '');
                $type = strtolower(trim((string) ($f['realType'] ?? $f['type'] ?? '')));
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }

            if (!in_array($type, ['blob', 'file'], true)) {
                continue;
            }

            $fileFields[] = $name;
        }

        if ($fileFields === []) {
            return [
                'fileWriterUses' => '',
                'fileWriterCtorArg' => '',
                'fileWriteLines' => '',
                'hasFiles' => '',
            ];
        }

        $uses = "use App\\Files\\FileWriter;\n";
        $ctorArg = ",\n        private FileWriter \$files";

        $lines = [];
        foreach ($fileFields as $field) {
            $payloadKey = $this->naming->camel($field);

            $lines[] =
                "        if (array_key_exists('{$payloadKey}', \$payload)) {\n" .
                "            \$value = \$payload['{$payloadKey}'];\n" .
                "            if (\$value === null || trim((string) \$value) === '') {\n" .
                "                \$p->{$field} = null;\n" .
                "            } else {\n" .
                "                \$stored = \$this->files->writeBase64((string) \$value, '{$ctx->entitySnake}-{$field}');\n" .
                "                \$p->{$field} = \$stored->path;\n" .
                "            }\n" .
                "        }\n\n";
        }

        return [
            'fileWriterUses' => $uses,
            'fileWriterCtorArg' => $ctorArg,
            'fileWriteLines' => implode('', $lines),
            'hasFiles' => '1',
        ];
    }

    private function securityVars(GenerationContext $ctx, bool $securityEnabled): array
    {
        if (!$securityEnabled) {
            return [
                'securityUses' => '',
                'securityCtorArg' => '',
                'securityCtorAssign' => '',
                'securityRequireList' => '',
                'securityRequireView' => '',
                'securityRequireCreate' => '',
                'securityRequireUpdate' => '',
                'securityRequireDelete' => '',
                'permRequireList' => '',
                'permRequireView' => '',
                'permRequireCreate' => '',
                'permRequireUpdate' => '',
                'permRequireDelete' => '',
            ];
        }

        $uses = "use App\\Security\\Rbac\\PermissionChecker;\n";
        $ctorArg = ",\n        private PermissionChecker \$perm";

        $reqList = "        \$this->perm->require('" . $ctx->permission('LIST') . "');\n";
        $reqView = "        \$this->perm->require('" . $ctx->permission('VIEW') . "');\n";
        $reqCreate = "        \$this->perm->require('" . $ctx->permission('CREATE') . "');\n";
        $reqUpdate = "        \$this->perm->require('" . $ctx->permission('UPDATE') . "');\n";
        $reqDelete = "        \$this->perm->require('" . $ctx->permission('DELETE') . "');\n";

        return [
            'securityUses' => $uses,
            'securityCtorArg' => $ctorArg,
            'securityCtorAssign' => '',
            'securityRequireList' => $reqList,
            'securityRequireView' => $reqView,
            'securityRequireCreate' => $reqCreate,
            'securityRequireUpdate' => $reqUpdate,
            'securityRequireDelete' => $reqDelete,
            'permRequireList' => $reqList,
            'permRequireView' => $reqView,
            'permRequireCreate' => $reqCreate,
            'permRequireUpdate' => $reqUpdate,
            'permRequireDelete' => $reqDelete,
        ];
    }

    private function namespaceFromPath(GenerationContext $ctx, string $absPath): string
    {
        $base = rtrim($ctx->path('app'), '/');
        $rel = str_replace($base . '/', '', $absPath);
        $dir = dirname($rel);
        $dir = $dir === '.' ? '' : $dir;

        $ns = 'App';
        if ($dir !== '') {
            $ns .= '\\' . str_replace('/', '\\', $dir);
        }

        return $ns;
    }
}
