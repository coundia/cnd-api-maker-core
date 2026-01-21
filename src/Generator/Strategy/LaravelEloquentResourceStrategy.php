<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Strategy;

use CndApiMaker\Core\Definition\FeaturesDefinition;
use CndApiMaker\Core\Generator\Common\DtoGenerator;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\GenerationResult;
use CndApiMaker\Core\Generator\Laravel\LaravelFactoryGenerator;
use CndApiMaker\Core\Generator\Laravel\LaravelMigrationGenerator;
use CndApiMaker\Core\Generator\Laravel\LaravelModelGenerator;
use CndApiMaker\Core\Generator\Laravel\LaravelCommunGenerator;
use CndApiMaker\Core\Generator\Laravel\LaravelTestsGenerator;

final readonly class LaravelEloquentResourceStrategy implements ResourceGenerationStrategy
{
    public function __construct(
        private DtoGenerator $dto,
        private LaravelModelGenerator $model,
        private LaravelCommunGenerator $state,
        private LaravelTestsGenerator $tests,
        private LaravelMigrationGenerator $migrations,
        private LaravelFactoryGenerator $factory
    ) {
    }

    public function supports(GenerationContext $ctx): bool
    {
        return $ctx->framework === 'laravel' && $ctx->def->driver === 'eloquent';
    }

    public function generate(GenerationContext $ctx): GenerationResult
    {
        $files = [];

        $dtoBase = $ctx->path('app/Dto/'.$ctx->entity);
        $dtoNs = 'App\\Dto\\'.$ctx->entity;


        $files = array_merge($files,
            $this->state->generate($ctx));

        $stack = $ctx->def->app->stack ?? 'api_platform';

        if (FeaturesDefinition::isEnabled($ctx, 'dto') && $stack!='native'){
            $files = array_merge($files,
                $this->dto->generate(
                    $dtoBase,
                    $dtoNs,
                    $ctx->entity,
                    $ctx->def->fields,
                    $ctx->groupsRead,
                    $ctx->groupsWrite,
                    $ctx->force,
                    $ctx->dryRun,
                    $ctx
                ));
        }

        if (FeaturesDefinition::isEnabled($ctx, 'factories')) {
            $files = array_merge($files, $this->factory->generate($ctx));
        }

        if (FeaturesDefinition::isEnabled($ctx, 'entity')){
            $files = array_merge($files,
                $this->model->generate($ctx));

            $files = array_merge($files,
                $this->migrations->generate($ctx));
        }

        if (FeaturesDefinition::isEnabled($ctx, 'tests')) {
            $files = array_merge($files, $this->tests->generate($ctx));
        }

        return new GenerationResult($files);
    }


}
