<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

use Symfony\Component\Yaml\Yaml;

class DefinitionLoader
{
	public function load(string $path): ResourceDefinition
	{
		if (!is_file($path)) {
			throw new \RuntimeException('Definition file not found: '.$path);
		}

		$ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
		$raw = match ($ext) {
			'json' => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
			'yml', 'yaml' => Yaml::parseFile($path),
			default => throw new \RuntimeException('Unsupported definition extension: '.$ext),
		};

		if (!is_array($raw)) {
			throw new \RuntimeException('Invalid definition format: expected object at root.');
		}

		return $this->hydrate($raw);
	}

	public function hydrate(array $raw): ResourceDefinition
	{
		$entity = (string) ($raw['entity'] ?? '');
		$table = (string) ($raw['table'] ?? '');
		$driver = (string) ($raw['driver'] ?? 'eloquent');
		$module = isset($raw['module']) ? (string) $raw['module'] : null;

		if ($entity === '' || $table === '') {
			throw new \RuntimeException('Definition must contain "entity" and "table".');
		}

		$apiRaw = is_array($raw['api'] ?? null) ? $raw['api'] : [];
		$api = new ApiDefinition(
			uriPrefix: (string) ($apiRaw['uriPrefix'] ?? '/'.strtolower($entity).'s'),
			groupsRead: (string) ($apiRaw['groupsRead'] ?? strtolower($entity).':read'),
			groupsWrite: (string) ($apiRaw['groupsWrite'] ?? strtolower($entity).':write'),
			uuid: (bool) ($apiRaw['uuid'] ?? true),
		);

		$featuresRaw = is_array($raw['features'] ?? null) ? $raw['features'] : [];
		$features = new FeaturesDefinition($featuresRaw);

		$testsRaw = is_array($raw['tests'] ?? null) ? $raw['tests'] : [];
		$tests = new TestsDefinition(
			enabled: (bool) ($testsRaw['enabled'] ?? true),
			framework: (string) ($testsRaw['framework'] ?? 'auto'),
			auth: (string) ($testsRaw['auth'] ?? 'none'),
		);

		$fields = [];
		foreach (($raw['fields'] ?? []) as $f) {
			if (!is_array($f)) {
				continue;
			}

			$name = (string) ($f['name'] ?? '');
			if ($name === '') {
				continue;
			}

			$fields[] = new FieldDefinition(
				name: $name,
				type: (string) ($f['type'] ?? 'string'),
				nullable: (bool) ($f['nullable'] ?? false),
				hidden: (bool) ($f['hidden'] ?? false),
				fillable: (bool) ($f['fillable'] ?? true),
				cast: isset($f['cast']) ? (string) $f['cast'] : null,
			);
		}

		$storage = $this->hydrateStorage($raw, $table, $fields, $features);

		$knownRoot = ['entity','table','driver','module','api','features','tests','fields','storage','security','app'];
		$extra = array_diff_key($raw, array_flip($knownRoot));

		return new ResourceDefinition(
			entity: $entity,
			table: $table,
			driver: $driver,
			module: $module,
			api: $api,
			features: $features,
			tests: $tests,
			fields: $fields,
			storage: $storage,
			raw: $raw,
			extra: $extra,
		);
	}

	private function hydrateStorage(array $raw, string $table, array $fields, FeaturesDefinition $features): StorageDefinition
	{
		$storageRaw = is_array($raw['storage'] ?? null) ? $raw['storage'] : [];

		$storage = new StorageDefinition();
		$storage->table = (string) ($storageRaw['table'] ?? $table);

		$storage->fillable = $this->resolveFillable($storageRaw, $fields, $features);
		$storage->hidden = $this->resolveHidden($storageRaw, $features);
		$storage->casts = $this->resolveCasts($storageRaw, $fields);

		return $storage;
	}

	private function resolveFillable(array $storageRaw, array $fields, FeaturesDefinition $features): array
	{
		$explicit = $storageRaw['fillable'] ?? null;
		if (is_array($explicit) && $explicit !== []) {
			return array_values(array_unique(array_map('strval', $explicit)));
		}

		$items = [];

		if ($features->tenant) {
			$items[] = 'tenant_id';
		}

		foreach ($fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}
			if (!$f->fillable) {
				continue;
			}

			$name = (string) $f->name;

			if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
				continue;
			}
			if ($name === 'tenant_id') {
				continue;
			}

			$items[] = $name;
		}

		if ($features->audit) {
			if (!in_array('created_by', $items, true)) {
				$items[] = 'created_by';
			}
			if (!in_array('updated_by', $items, true)) {
				$items[] = 'updated_by';
			}
		}

		return array_values(array_unique($items));
	}

	private function resolveHidden(array $storageRaw, FeaturesDefinition $features): array
	{
		$explicit = $storageRaw['hidden'] ?? null;
		if (is_array($explicit)) {
			return array_values(array_unique(array_map('strval', $explicit)));
		}

		return $features->tenant ? ['tenant_id'] : [];
	}

	private function resolveCasts(array $storageRaw, array $fields): array
	{
		$explicit = $storageRaw['casts'] ?? null;
		if (is_array($explicit) && $explicit !== []) {
			$out = [];
			foreach ($explicit as $k => $v) {
				$k = (string) $k;
				if ($k === '') {
					continue;
				}
				$out[$k] = (string) $v;
			}
			return $out;
		}

		$casts = [];
		foreach ($fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}
			if ($f->cast === null || $f->cast === '') {
				continue;
			}

			$casts[(string) $f->name] = (string) $f->cast;
		}

		return $casts;
	}
}
