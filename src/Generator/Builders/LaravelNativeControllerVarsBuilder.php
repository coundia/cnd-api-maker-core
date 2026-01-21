<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Laravel\Support\LaravelCommunContext;

final readonly class LaravelNativeControllerVarsBuilder
{
    public function vars(GenerationContext $ctx, LaravelCommunContext $s, array $securityVars): array
    {
        $tenantEnabled = (bool) $ctx->def->features->enabled('tenant');
        $softDeletesEnabled = (bool) $ctx->def->features->enabled('softDeletes');
        $securityEnabled = (bool) $ctx->def->features->enabled('security');

        $securityUses = $securityEnabled ? "use App\\Security\\Rbac\\PermissionChecker;\n" : '';
        $securityCtor = $securityEnabled ? "    public function __construct(private PermissionChecker \$perm) {}\n\n" : '';


        $storeRequestFqn = 'App\\Http\\Requests\\'.$ctx->entity.'StoreRequest';
        $updateRequestFqn = 'App\\Http\\Requests\\'.$ctx->entity.'UpdateRequest';
        $resourceFqn = 'App\\Http\\Resources\\'.$ctx->entity.'Resource';

        $tenantResolveLine = $tenantEnabled
            ? "        \$tenantId = app(\\App\\Tenancy\\TenantContext::class)->requireId();\n"
            : '';

        $tenantWhereIndex = $tenantEnabled
            ? "        \$q->where('tenant_id', \$tenantId);\n"
            : '';

        $tenantSetOnCreate = $tenantEnabled
            ? "        \$payload['tenant_id'] = \$tenantId;\n"
            : '';

        $findOneLine = $tenantEnabled
            ? "        \$p = {$ctx->entity}::query()->where('tenant_id', \$tenantId)->findOrFail(\$id);\n"
            : "        \$p = {$ctx->entity}::query()->findOrFail(\$id);\n";

        $softDeleteOrDeleteLine = $softDeletesEnabled
            ? "        \$p->delete();\n"
            : "        \$p->delete();\n";

        return [
            'namespace' => 'App\\Http\\Controllers',
            'entity' => $ctx->entity,

            'modelFqn' => $s->modelFqn,

            'storeRequestFqn' => $storeRequestFqn,
            'updateRequestFqn' => $updateRequestFqn,
            'resourceFqn' => $resourceFqn,

            'storeRequestClass' => $ctx->entity.'StoreRequest',
            'updateRequestClass' => $ctx->entity.'UpdateRequest',
            'resourceClass' => $ctx->entity.'Resource',

            'itemsPerPageDefault' => '50',
            'filtersApply' => '',

            'tenantResolveLine' => $tenantResolveLine,
            'tenantWhereIndex' => $tenantWhereIndex,
            'tenantSetOnCreate' => $tenantSetOnCreate,
            'findOneLine' => $findOneLine,
            'applyLines' => '',
            'createDefaultsLines' => '',
            'softDeleteOrDeleteLine' => $softDeleteOrDeleteLine,

            'securityUses' => $securityUses,
            'securityController' => $securityCtor,

            'permRequireList' => (string) ($securityVars['permRequireList'] ?? ''),
            'permRequireView' => (string) ($securityVars['permRequireView'] ?? ''),
            'permRequireCreate' => (string) ($securityVars['permRequireCreate'] ?? ''),
            'permRequireUpdate' => (string) ($securityVars['permRequireUpdate'] ?? ''),
            'permRequireDelete' => (string) ($securityVars['permRequireDelete'] ?? ''),
        ];
    }
}
