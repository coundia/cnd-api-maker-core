<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Builders;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class LaravelNativeRoutesVarsBuilder
{
    public function __construct(
        private Naming $naming
    ) {
    }

    public function vars(GenerationContext $ctx): array
    {
        $uri = '/'.trim((string) $ctx->uriPrefix, '/');
        $uri = $uri === '/' ? '' : $uri;

        $parts = $uri === '' ? [] : array_values(array_filter(explode('/', trim($uri, '/'))));
        $resourcePath = $parts !== [] ? (string) array_pop($parts) : $this->naming->plural($ctx->entitySnake);

        $routePrefix = $parts === [] ? '' : implode('/', $parts);

        $controllerFqn = 'App\\Http\\Controllers\\'.$ctx->entity.'Controller';
        $controllerClass = $ctx->entity.'Controller';

        $middlewares = $this->middlewares($ctx);

        $useControllerLine = 'use '.$controllerFqn.';';
        $routeGroupBlock = $this->routeGroupBlock($routePrefix, $middlewares, $resourcePath, $controllerClass);

        return [
            'controllerFqn' => $controllerFqn,
            'controllerClass' => $controllerClass,
            'routePrefix' => $routePrefix,
            'resourcePath' => $resourcePath,
            'middlewares' => $middlewares,

            'useControllerLine' => $useControllerLine,
            'routeGroupBlock' => $routeGroupBlock,
            'routeNeedle' => "Route::apiResource('".$resourcePath."', ".$controllerClass."::class);",
        ];
    }

    private function routeGroupBlock(string $routePrefix, string $middlewares, string $resourcePath, string $controllerClass): string
    {
        $prefixLine = $routePrefix !== ''
            ? "    ->prefix('".$routePrefix."')\n"
            : '';

        return "Route::middleware(".$middlewares.")\n"
            .$prefixLine
            ."    ->group(function (): void {\n"
            ."        Route::apiResource('".$resourcePath."', ".$controllerClass."::class);\n"
            ."    });";
    }

    private function middlewares(GenerationContext $ctx): string
    {
        $items = ['api'];

        if ($ctx->def->features->enabled('security')) {
            $items[] = 'auth:sanctum';
        }

        $tenancyMw = (string) (config('tenancy.middleware', '') ?? '');
        $tenancyMw = trim($tenancyMw);

        if ($ctx->def->features->enabled('tenant') && $tenancyMw !== '') {
            $items[] = $tenancyMw;
        }

        $php = array_map(static fn (string $m) => "'".$m."'", $items);

        return '['.implode(', ', $php).']';
    }
}
