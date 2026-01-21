<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\LaravelNative;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelNativeHttpGenerator
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
        $files = [];

        if ($ctx->def->features->enabled('controllers')) {
            $tpl = $this->stubs->get('laravel_native/controller.api');
            $content = $this->renderer->render($tpl, [
                'namespace' => 'App\\Http\\Controllers\\Api',
                'entity' => $ctx->entity,
                'modelFqn' => 'App\\Models\\'.$ctx->entity,
                'requestFqn' => 'App\\Http\\Requests\\'.$ctx->entity.'Request',
                'resourceFqn' => 'App\\Http\\Resources\\'.$ctx->entity.'Resource',
            ]);

            $path = $ctx->path('app/Http/Controllers/Api/'.$ctx->entity.'Controller.php');
            $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);
            $files[] = ['path' => $path, 'type' => 'controller'];
        }

        if ($ctx->def->features->enabled('requests')) {
            $tpl = $this->stubs->get('laravel_native/request');
            $content = $this->renderer->render($tpl, [
                'namespace' => 'App\\Http\\Requests',
                'entity' => $ctx->entity,
            ]);

            $path = $ctx->path('app/Http/Requests/'.$ctx->entity.'Request.php');
            $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);
            $files[] = ['path' => $path, 'type' => 'request'];
        }

        if ($ctx->def->features->enabled('resources')) {
            $tpl = $this->stubs->get('laravel_native/resource');
            $content = $this->renderer->render($tpl, [
                'namespace' => 'App\\Http\\Resources',
                'entity' => $ctx->entity,
            ]);

            $path = $ctx->path('app/Http/Resources/'.$ctx->entity.'Resource.php');
            $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);
            $files[] = ['path' => $path, 'type' => 'resource'];
        }

        if ($ctx->def->features->enabled('routes')) {
            $tpl = $this->stubs->get('laravel_native/routes.api_append');
            $content = $this->renderer->render($tpl, [
                'entity' => $ctx->entity,
                'uriPrefix' => $ctx->uriPrefix,
                'segment' => $this->naming->plural($ctx->entity),
                'controllerFqn' => 'App\\Http\\Controllers\\Api\\'.$ctx->entity.'Controller',
            ]);

            $path = $ctx->path('routes/api.php');
            $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);
            $files[] = ['path' => $path, 'type' => 'routes'];
        }

        return $files;
    }
}
