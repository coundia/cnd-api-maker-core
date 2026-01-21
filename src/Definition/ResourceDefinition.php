<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

final class ResourceDefinition
{
    public function __construct(
		public string $entity,
		public string $table,
		public string $driver,
		public ?string $module,
		public ApiDefinition $api,
		public FeaturesDefinition $features,
		public TestsDefinition $tests,
		public array $fields,
		public StorageDefinition $storage,
		public array $raw = [],
		public array $extra = [],
	) {
	}

    private function hasFilesField(): bool
    {
        $fields = $this->fields;

        if (!is_array($fields)) {
            return false;
        }

        foreach ($fields as $f) {
            if (!is_object($f)) {
                continue;
            }

            $type = strtolower(trim((string) ($f->type ?? $f->realType ?? '')));
            $name = strtolower(trim((string) ($f->name ?? '')));

            if ($type === '') {
                continue;
            }

            if (in_array($type, ['file', 'files', 'blob', 'binary', 'image', 'upload'], true)) {
                return true;
            }

            if ($type === 'string' && str_contains($name, 'file')) {
                return true;
            }
        }

        return false;
    }
}
