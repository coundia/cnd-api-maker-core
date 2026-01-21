<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Generator\GenerationContext;

final readonly class LaravelNativeRequestVarsBuilder
{
    public function __construct(
        private LaravelNativeRequestRulesBuilder $rules
    ) {
    }

    public function storeVars(GenerationContext $ctx): array
    {
        return [
            'namespace' => 'App\\Http\\Requests',
            'entity' => $ctx->entity,
            'validationRulesCreate' => $this->rules->buildCreate($ctx->def->fields ?? []),
        ];
    }

    public function updateVars(GenerationContext $ctx): array
    {
        return [
            'namespace' => 'App\\Http\\Requests',
            'entity' => $ctx->entity,
            'validationRulesUpdate' => $this->rules->buildUpdate($ctx->def->fields ?? []),
        ];
    }
}
