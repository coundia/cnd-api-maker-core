<?php
declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel\Support;

use CndApiMaker\Core\Generator\GenerationContext;

final class LaravelCommunFiles{
    public static function all(GenerationContext $ctx,
        LaravelCommunContext $s): array{
        $e = $ctx->entity;

        $stack = strtolower(trim((string)($ctx->def->app->stack ?? $ctx->stack ?? 'api-platform')));
        $secPath = $ctx->path('app/Security/Rbac');
        $appFiles = $ctx->path('app');
        $testSrc = $ctx->path('tests');
        $databaseSrc = $ctx->path('database');
        $config = $ctx->path('config');
        $bootstrap = $ctx->path('bootstrap');

        $hasTenant = $ctx->def->features->enabled("tenant");
        $hasSecurity = $ctx->def->features->enabled('security');

        $files = [];

        //APiResource
        $files[] = new LaravelCommunFileSpec('Health',
            'laravel/ApiResource/Health',
            $appFiles . '/ApiResource/Health.php',
            'ApiResource');

        //Factories
        $files[] = new LaravelCommunFileSpec('PermissionFactory',
            'laravel/Factories/PermissionFactory',
            $databaseSrc . '/factories/PermissionFactory.php',
            'factories');

        $files[] = new LaravelCommunFileSpec('TenantFactory',
            'laravel/Factories/TenantFactory',
            $databaseSrc . '/factories/TenantFactory.php',
            'factories');

        //config
        $files[] = new LaravelCommunFileSpec('api-platform',
            'laravel/config/api-platform',
            $config . '/api-platform.php',
            'config');
        $files[] = new LaravelCommunFileSpec('app',
            'laravel/config/app',
            $bootstrap . '/app.php',
            'config');
        $files[] = new LaravelCommunFileSpec('providers',
            'laravel/config/providers',
            $bootstrap . '/providers.php',
            'config');
        //cmd
        $files[] = new LaravelCommunFileSpec('GeneratePermissionsCommand',
            'laravel/Console/GeneratePermissionsCommand',
            $appFiles . '/Console/Commands/GeneratePermissionsCommand.php',
            'Console');
        //Providers
        $files[] = new LaravelCommunFileSpec('TenancyServiceProvider',
            'laravel/tenant/TenancyServiceProvider',
            $appFiles . '/Providers/TenancyServiceProvider.php',
            'Providers');

        //middleware
        $files[] = new LaravelCommunFileSpec('Authenticate',
            'laravel/Middleware/Authenticate',
            $appFiles . '/Tenancy/Http/Middleware/Authenticate.php',
            'Middleware');
        $files[] = new LaravelCommunFileSpec('ResolveTenant',
            'laravel/Middleware/ResolveTenant',
            $appFiles . '/Tenancy/Http/Middleware/ResolveTenant.php',
            'Middleware');
        //docs
        if ($stack == 'native'){
            $openApiBase = $ctx->path('app/OpenApi');
            $providerBase = $ctx->path('app/Providers');

            $files[] = new LaravelCommunFileSpec('openapi_paths_contributor',
                'laravel/openapi/PathsContributor',
                $openApiBase . '/PathsContributor.php',
                'openapi');
            $files[] = new LaravelCommunFileSpec('openapi_factory',
                'laravel/openapi/OpenApiFactory',
                $openApiBase . '/OpenApiFactory.php',
                'openapi');
            $files[] = new LaravelCommunFileSpec('openapi_provider',
                'laravel/openapi/OpenApiServiceProvider',
                $providerBase . '/OpenApiServiceProvider.php',
                'openapi');

            $files[] = new LaravelCommunFileSpec('openapi_health_paths',
                'laravel/openapi/HealthPaths',
                $openApiBase . '/Health/HealthPaths.php',
                'openapi');

            $openApiEntityDir = $openApiBase . '/' . $e . 's';
            $files[] = new LaravelCommunFileSpec('openapi_entity_paths',
                'laravel/openapi/EntityPaths',
                $openApiEntityDir . '/' . $e . 'Paths.php',
                'openapi');
            $files[] = new LaravelCommunFileSpec('openapi_entity_schemas',
                'laravel/openapi/EntitySchemas',
                $openApiEntityDir . '/' . $e . 'Schemas.php',
                'openapi');
        }

        if ($hasSecurity){

            $files[] = new LaravelCommunFileSpec('PermissionChecker',
                'laravel/PermissionChecker',
                $secPath . '/PermissionChecker.php',
                'security');
            $files[] = new LaravelCommunFileSpec('GrantsRbacPermissions',
                'laravel/GrantsRbacPermissions',
                $secPath . '/GrantsRbacPermissions.php',
                'security');
            //auth
            $files[] = new LaravelCommunFileSpec('AuthResource',
                'laravel/Auth/AuthResource',
                $appFiles . '/Models/AuthResource.php',
                'security');
            $files[] = new LaravelCommunFileSpec('LoginInput',
                'laravel/Auth/LoginInput',
                $appFiles . '/Dto/Auth/LoginInput.php',
                'security');
            $files[] = new LaravelCommunFileSpec('LoginOutput',
                'laravel/Auth/LoginOutput',
                $appFiles . '/Dto/Auth/LoginOutput.php',
                'security');
            $files[] = new LaravelCommunFileSpec('RegisterInput',
                'laravel/Auth/RegisterInput',
                $appFiles . '/Dto/Auth/RegisterInput.php',
                'security');
            $files[] = new LaravelCommunFileSpec('RegisterOutput',
                'laravel/Auth/RegisterOutput',
                $appFiles . '/Dto/Auth/RegisterOutput.php',
                'security');
            $files[] = new LaravelCommunFileSpec('RegisterProcessor',
                'laravel/Auth/RegisterProcessor',
                $appFiles . '/State/Auth/RegisterProcessor.php',
                'security');
            $files[] = new LaravelCommunFileSpec('LoginProcessor',
                'laravel/Auth/LoginProcessor',
                $appFiles . '/State/Auth/LoginProcessor.php',
                'security');
            $files[] = new LaravelCommunFileSpec('AuthApiTest',
                'laravel/Auth/AuthApiTest',
                $testSrc . '/Feature/Security/AuthApiTest.php',
                'security');
            //Model
            $files[] = new LaravelCommunFileSpec('Tenant',
                'laravel/tenant/Tenant',
                $appFiles . '/Models/Tenant.php',
                'security');
            $files[] = new LaravelCommunFileSpec('Permission',
                'laravel/security/Permission',
                $appFiles . '/Models/Permission.php',
                'security');
            $files[] = new LaravelCommunFileSpec('Role',
                'laravel/security/Role',
                $appFiles . '/Models/Role.php',
                'security');
            $files[] = new LaravelCommunFileSpec('RolePermission',
                'laravel/security/RolePermission',
                $appFiles . '/Models/RolePermission.php',
                'security');
            $files[] = new LaravelCommunFileSpec('User',
                'laravel/security/User',
                $appFiles . '/Models/User.php',
                'security');
            $files[] = new LaravelCommunFileSpec('UserRole',
                'laravel/security/UserRole',
                $appFiles . '/Models/UserRole.php',
                'security');
            //migrations

            //token
            $files[] = new LaravelCommunFileSpec('0001_01_01_100000_personal_access_tokens',
                'laravel/migrations/0001_01_01_100000_personal_access_tokens',
                $databaseSrc . '/migrations/0001_01_01_100000_cnd_create_personal_access_tokens.php',
                'security');
            //seeders
            if ($hasTenant){
                $files[] = new LaravelCommunFileSpec('SecuritySeederTenant',
                    'laravel/security/SecuritySeederTenant',
                    $databaseSrc . '/seeders/SecuritySeederTenant.php',
                    'security');

                $files[] = new LaravelCommunFileSpec('DatabaseSeeder',
                    'laravel/security/DatabaseSeeder',
                    $databaseSrc . '/seeders/DatabaseSeeder.php',
                    'security');
            }
        }

        if ($stack === 'native'){
            $httpBase = $ctx->path('app/Http');

            $files[] = new LaravelCommunFileSpec('controller',
                'laravel/controller.native',
                $httpBase . '/Controllers/' . $e . 'Controller.php',
                'http');
            $files[] = new LaravelCommunFileSpec('request_store',
                'laravel/request.store.native',
                $httpBase . '/Requests/' . $e . 'StoreRequest.php',
                'http');
            $files[] = new LaravelCommunFileSpec('request_update',
                'laravel/request.update.native',
                $httpBase . '/Requests/' . $e . 'UpdateRequest.php',
                'http');
            $files[] = new LaravelCommunFileSpec('resource',
                'laravel/resource.native',
                $httpBase . '/Resources/' . $e . 'Resource.php',
                'http');
            $files[] = new LaravelCommunFileSpec('routes',
                'laravel/routes.native.append',
                $ctx->path('routes/api.php'),
                'routes');

            return $files;
        }
        //for files
        if ($ctx->hasFiles()){
            $files[] = new LaravelCommunFileSpec('Base64File',
                'laravel/Files/Base64File',
                $appFiles . '/Files/Base64File.php',
                'files');
            $files[] = new LaravelCommunFileSpec('FileReader',
                'laravel/Files/FileReader',
                $appFiles . '/Files/FileReader.php',
                'files');
            $files[] = new LaravelCommunFileSpec('FileReadResult',
                'laravel/Files/FileReadResult',
                $appFiles . '/Files/FileReadResult.php',
                'files');
            $files[] = new LaravelCommunFileSpec('FileWriter',
                'laravel/Files/FileWriter',
                $appFiles . '/Files/FileWriter.php',
                'files');
            $files[] = new LaravelCommunFileSpec('FileWriteResult',
                'laravel/Files/FileWriteResult',
                $appFiles . '/Files/FileWriteResult.php',
                'files');
        }//end files

        //tenant

        $files[] = new LaravelCommunFileSpec('TenantContext',
            'laravel/tenant/TenantContext',
            $appFiles . '/Tenancy/TenantContext.php',
            'tenant');
        $files[] = new LaravelCommunFileSpec('TenantOwned',
            'laravel/tenant/TenantOwned',
            $appFiles . '/Models/Concerns/TenantOwned.php',
            'tenant');

        if ($hasTenant){
            $files[] = new LaravelCommunFileSpec('GrantsRbacPermissionsTenant',
                'laravel/tenant/GrantsRbacPermissionsTenant',
                $secPath . '/GrantsRbacPermissionsTenant.php',
                'tenant');
        }
        //tenant end

        // $files[] = new LaravelCommunFileSpec('mapper', 'laravel/state.mapper', $s->stateBase.'/'.$e.'Mapper.php', 'state');
        $files[] = new LaravelCommunFileSpec('repository',
            'laravel/state.repository',
            $s->stateBase . '/' . $e . 'Repository.php',
            'state');
        $files[] = new LaravelCommunFileSpec('payload_resolver',
            'laravel/state.payload_resolver',
            $s->stateBase . '/' . $e . 'PayloadResolver.php',
            'state');
        $files[] = new LaravelCommunFileSpec('writer',
            'laravel/state.writer',
            $s->stateBase . '/' . $e . 'Writer.php',
            'state');

        $files[] = new LaravelCommunFileSpec('collection_provider',
            'laravel/state.collection_provider',
            $s->stateBase . '/' . $e . 'CollectionProvider.php',
            'state');
        $files[] = new LaravelCommunFileSpec('item_provider',
            'laravel/state.item_provider',
            $s->stateBase . '/' . $e . 'ItemProvider.php',
            'state');
        $files[] = new LaravelCommunFileSpec('write_processor',
            'laravel/state.write_processor',
            $s->stateBase . '/' . $e . 'WriteProcessor.php',
            'state');
        $files[] = new LaravelCommunFileSpec('delete_processor',
            'laravel/state.delete_processor',
            $s->stateBase . '/' . $e . 'DeleteProcessor.php',
            'state');

        return $files;
    }
}
