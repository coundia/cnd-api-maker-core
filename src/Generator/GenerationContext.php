<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator;

use CndApiMaker\Core\Definition\ResourceDefinition;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class GenerationContext
{
    public string $entity;
    public string $entitySnake;
    public string $opBase;
    public string $uriPrefix;
    public string $groupsRead;
    public string $groupsWrite;
    public string $permissionPrefix;
    public string $idRequirement;

    public bool $securityEnabled;
    public string $securityPrefix;
    public array $defaultPermissions;

    public function __construct(
        public ResourceDefinition $def,
        public string $framework,
        public string $basePath,
        public bool $force,
        public bool $dryRun,
        string $entity,
        string $entitySnake,
        string $opBase,
        string $uriPrefix,
        string $groupsRead,
        string $groupsWrite,
        string $permissionPrefix,
        string $idRequirement,
        bool $securityEnabled,
        string $securityPrefix,
        array $defaultPermissions
    ) {
        $this->entity = $entity;
        $this->entitySnake = $entitySnake;
        $this->opBase = $opBase;
        $this->uriPrefix = $uriPrefix;
        $this->groupsRead = $groupsRead;
        $this->groupsWrite = $groupsWrite;
        $this->permissionPrefix = $permissionPrefix;
        $this->idRequirement = $idRequirement;
        $this->securityEnabled = $securityEnabled;
        $this->securityPrefix = $securityPrefix;
        $this->defaultPermissions = $defaultPermissions;
    }

    public static function from(
        ResourceDefinition $def,
        string $framework,
        string $basePath,
        bool $force,
        bool $dryRun,
        array $globalConfig = []
    ): self {
        $naming = new Naming();

        $entity = $naming->studly($def->entity);
        $entitySnake = $naming->snake($entity);

        $prefixConfig = $globalConfig["api"]["uriPrefix"] ?? '';
        $uriPrefix = (string) ($def->api->uriPrefix ?? '');
        $uriPrefix = $prefixConfig.$uriPrefix;
        $groupsRead = (string) ($def->api->groupsRead ?? ($entitySnake.':read'));
        $groupsWrite = (string) ($def->api->groupsWrite ?? ($entitySnake.':write'));

        $permissionPrefix = $entitySnake;
        $opBase = $entitySnake;

        $idRequirement = $def->api->uuid ? '[0-9a-fA-F-]{36}' : '\d+';

        $securityEnabled = (bool) (($globalConfig['security']['enabled'] ?? null) ?? false);
        if (is_object($def->features) && method_exists($def->features, 'enabled')) {
            $securityEnabled = $securityEnabled && (bool) $def->features->enabled('security');
        }

        $securityPrefix = (string) (($globalConfig['security']['prefix'] ?? null) ?? 'APP_');
        $defaultPermissions = (array) (($globalConfig['security']['defaultPermissions'] ?? null) ?? ['LIST', 'VIEW', 'CREATE', 'UPDATE', 'DELETE']);

        return new self(
            $def,
            $framework,
            $basePath,
            $force,
            $dryRun,
            $entity,
            $entitySnake,
            $opBase,
            $uriPrefix,
            $groupsRead,
            $groupsWrite,
            $permissionPrefix,
            $idRequirement,
            $securityEnabled,
            $securityPrefix,
            $defaultPermissions
        );
    }

    public function path(string $relative): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relative, DIRECTORY_SEPARATOR);
    }

    public function permission(string $action): string
    {
        return  strtoupper($this->permissionPrefix).':'.strtoupper($action);
    }

    public function hasFiles(): bool
    {

         $ctx = $this;

        if (is_object($ctx->def) && property_exists($ctx->def, 'hasFiles') && is_bool($ctx->def->hasFiles)) {
            return $ctx->def->hasFiles;
        }

        $fields = is_array($ctx->def->fields ?? null) ? $ctx->def->fields : [];
        $has = $this->detectHasFiles($fields);

        if (is_object($ctx->def) && property_exists($ctx->def, 'hasFiles')) {
            $ctx->def->hasFiles = $has;
        }

        return $has;
    }

    private function detectHasFiles(array $fields): bool
    {
        foreach ($fields as $f) {
            $type = null;
            $format = null;

            if (is_array($f)) {
                $type = $f['type'] ?? $f['realType'] ?? null;
                $format = $f['format'] ?? null;
                $storage = $f['storage'] ?? null;

                if (is_string($storage) && strtolower($storage) === 'file') {
                    return true;
                }
            } elseif (is_object($f)) {
                $type = $f->type ?? $f->realType ?? null;
                $format = $f->format ?? null;
                $storage = $f->storage ?? null;

                if (is_string($storage) && strtolower($storage) === 'file') {
                    return true;
                }
            }

            $t = is_string($type) ? strtolower(trim($type)) : '';
            $fmt = is_string($format) ? strtolower(trim($format)) : '';

            if (in_array($t, ['file', 'files', 'blob', 'binary', 'document', 'image'], true)) {
                return true;
            }

            if (in_array($fmt, ['binary', 'base64', 'byte'], true)) {
                return true;
            }
        }

        return false;
    }
}
