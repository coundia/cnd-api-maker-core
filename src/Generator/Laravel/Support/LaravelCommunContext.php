<?php

declare(strict_types=1);
namespace CndApiMaker\Core\Generator\Laravel\Support;
use CndApiMaker\Core\Generator\GenerationContext;

final readonly class LaravelCommunContext
{
    public function __construct(
        public string $stateNs,
        public string $dtoNs,
        public string $modelFqn,
        public string $inputFqn,
        public string $outputFqn,
        public string $stateBase,
        public string $repoFqn,
        public string $mapperFqn,
        public string $payloadResolverFqn,
        public string $writerFqn
    ) {
    }

    public static function from(GenerationContext $ctx): self
    {
        $stateNs = 'App\\State\\'.$ctx->entity;
        $dtoNs = 'App\\Dto\\'.$ctx->entity;

        $modelFqn = 'App\\Models\\'.$ctx->entity;
        $inputFqn = $dtoNs.'\\'.$ctx->entity.'Input';
        $outputFqn = $dtoNs.'\\'.$ctx->entity.'Output';

        $stateBase = $ctx->path('app/State/'.$ctx->entity);

        $repoFqn = $stateNs.'\\'.$ctx->entity.'Repository';
        $mapperFqn = $stateNs.'\\'.$ctx->entity.'Mapper';
        $payloadResolverFqn = $stateNs.'\\'.$ctx->entity.'PayloadResolver';
        $writerFqn = $stateNs.'\\'.$ctx->entity.'Writer';

        return new self(
            $stateNs,
            $dtoNs,
            $modelFqn,
            $inputFqn,
            $outputFqn,
            $stateBase,
            $repoFqn,
            $mapperFqn,
            $payloadResolverFqn,
            $writerFqn
        );
    }
}
