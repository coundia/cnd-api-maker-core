<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

use CndApiMaker\Core\Definition\ApiDefinition;
use CndApiMaker\Core\Definition\FeaturesDefinition;
use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Definition\ResourceDefinition;
use CndApiMaker\Core\Definition\StorageDefinition;
use CndApiMaker\Core\Definition\TestsDefinition;
use CndApiMaker\Core\Generator\Support\Naming;

final readonly class JdlToResourceMapper
{
	public function __construct(
		private Naming $naming
	) {}

	/** @return ResourceDefinition[] */
	public function map(JdlDocument $doc): array
	{
		$resourcesByEntity = [];

		foreach ($doc->entities as $entityName => $entity) {
			$studly = $this->naming->studly($entityName);
			$snake = $this->naming->snake($studly);
			$table = $this->naming->plural($snake);

			$fields = [];
			foreach ($entity->fields as $f) {
				$fields[] = new FieldDefinition(
					name: $this->naming->snake($f->name),
					type: $this->mapType($f->type),
					nullable: !$f->required,
					hidden: false,
					fillable: true,
					cast: $this->mapCast($f->type)
				);
			}

			$api = new ApiDefinition(
				uriPrefix: rtrim($doc->config->uriPrefix, '/').'/'.$this->naming->plural($snake),
				groupsRead: strtolower($snake).':read',
				groupsWrite: strtolower($snake).':write',
				uuid: $doc->config->uuid
			);

			$features = new FeaturesDefinition();

			$tests = new TestsDefinition(true, 'auto', 'none');

			$storage = new StorageDefinition();
			$storage->table = $table;
			$storage->fillable = array_values(array_map(static fn (FieldDefinition $ff) => $ff->name, $fields));
			$storage->casts = $this->castsFromFields($fields);

			$resourcesByEntity[$studly] = new ResourceDefinition(
				entity: $studly,
				table: $table,
				driver: $doc->config->driver,
				module: null,
				api: $api,
				features: $features,
				tests: $tests,
				fields: $fields,
				storage: $storage
			);
		}

		$this->applyRelations($doc, $resourcesByEntity);

		return array_values($resourcesByEntity);
	}

	private function mapType(string $jdlType): string
	{
		$t = strtolower($jdlType);

		return match ($t) {
			'string', 'text' => 'string',
			'integer', 'int' => 'int',
			'long' => 'bigint',
			'boolean', 'bool' => 'bool',
			'float', 'double', 'bigdecimal', 'decimal' => 'decimal',
			'instant', 'zoneddatetime', 'datetime' => 'datetime',
			'localdate', 'date' => 'date',
			'uuid' => 'uuid',
			default => $jdlType,
		};
	}

	private function mapCast(string $jdlType): ?string
	{
		$t = strtolower($jdlType);

		return match ($t) {
			'integer', 'int' => 'int',
			'long' => 'int',
			'boolean', 'bool' => 'bool',
			'float', 'double', 'bigdecimal', 'decimal' => 'float',
			'instant', 'zoneddatetime', 'datetime' => 'datetime',
			'localdate', 'date' => 'date',
			default => $jdlType,
		};
	}

	/** @param FieldDefinition[] $fields */
	private function castsFromFields(array $fields): array
	{
		$out = [];
		foreach ($fields as $f) {
			if ($f->cast !== null && $f->cast !== '') {
				$out[$f->name] = $f->cast;
			}
		}
		return $out;
	}

	private function defaultCollectionName(string $targetEntity): string
	{
		$snake = $this->naming->snake($this->naming->studly($targetEntity));
		return $this->naming->plural($snake);
	}

	private function applyRelations(JdlDocument $doc, array &$resourcesByEntity): void
	{
		foreach ($doc->relations as $r) {
			$from = $this->naming->studly($r->fromEntity);
			$to = $this->naming->studly($r->toEntity);

			if (!isset($resourcesByEntity[$from]) || !isset($resourcesByEntity[$to])) {
				continue;
			}

			$fromField = $r->fromField ?? $this->naming->camel($to);
			$toField = $r->toField ?? null;

			if ($r->kind === 'ManyToOne') {
				$this->addOwningRelationField($resourcesByEntity[$from], $fromField, 'ManyToOne', $to, null, null);
				continue;
			}

			if ($r->kind === 'OneToOne') {
				$this->addOwningRelationField($resourcesByEntity[$from], $fromField, 'OneToOne', $to, null, $toField !== null ? $this->naming->camel($toField) : null);
				if ($toField !== null) {
					$this->addInverseRelationField($resourcesByEntity[$to], $toField, 'OneToOne', $from, $this->naming->camel($fromField));
				}
				continue;
			}

			if ($r->kind === 'OneToMany') {
				if ($toField !== null) {
					$this->addOwningRelationField($resourcesByEntity[$to], $toField, 'ManyToOne', $from, null, null);
					$inverseCollection = $this->defaultCollectionName($to);
					$this->addInverseCollectionField($resourcesByEntity[$from], $inverseCollection, 'OneToMany', $to, $this->naming->camel($toField));
				}
				continue;
			}

			if ($r->kind === 'ManyToMany') {
				$leftCollection = $this->defaultCollectionName($to);
				$rightCollection = $this->defaultCollectionName($from);

				$left = $this->sanitizeRelationField($fromField, $leftCollection);
				$right = $this->sanitizeRelationField($toField ?? $rightCollection, $rightCollection);

				$this->addOwningCollectionField($resourcesByEntity[$from], $left, 'ManyToMany', $to, $this->naming->camel($right));
				$this->addInverseManyToManyField($resourcesByEntity[$to], $right, $from, $this->naming->camel($left));
			}
		}
	}

	private function sanitizeRelationField(string $field, string $fallback): string
	{
		$f = trim($field);
		return $f !== '' ? $this->naming->camel($f) : $fallback;
	}

	private function addOwningRelationField(ResourceDefinition $resourceDef, string $name, string $kind, string $target, ?string $mappedBy, ?string $inversedBy): void
	{
		$f = new FieldDefinition(
			name: $this->naming->snake($name),
			type: 'relation',
			nullable: true,
			hidden: false,
			fillable: true,
			cast: null
		);

		$f->relationKind = $kind;
		$f->targetEntity = $target;
		$f->isCollection = false;
		$f->mappedBy = $mappedBy !== null ? $this->naming->camel($mappedBy) : null;
		$f->inversedBy = $inversedBy !== null ? $this->naming->camel($inversedBy) : null;
		$f->isOwningSide = true;

		$resourceDef->fields[] = $f;
	}

	private function addInverseRelationField(ResourceDefinition $resourceDef, string $name, string $kind, string $target, string $mappedBy): void
	{
		$f = new FieldDefinition(
			name: $this->naming->snake($name),
			type: 'relation',
			nullable: true,
			hidden: false,
			fillable: false,
			cast: null
		);

		$f->relationKind = $kind;
		$f->targetEntity = $target;
		$f->isCollection = false;
		$f->mappedBy = $this->naming->camel($mappedBy);
		$f->isOwningSide = false;

		$resourceDef->fields[] = $f;
	}

	private function addInverseCollectionField(ResourceDefinition $resourceDef, string $name, string $kind, string $target, string $mappedBy): void
	{
		$f = new FieldDefinition(
			name: $this->naming->snake($name),
			type: 'relation',
			nullable: false,
			hidden: false,
			fillable: false,
			cast: null
		);

		$f->relationKind = $kind;
		$f->targetEntity = $target;
		$f->isCollection = true;
		$f->mappedBy = $this->naming->camel($mappedBy);
		$f->isOwningSide = false;

		$resourceDef->fields[] = $f;
	}

	private function addOwningCollectionField(ResourceDefinition $resourceDef, string $name, string $kind, string $target, ?string $inversedBy): void
	{
		$f = new FieldDefinition(
			name: $this->naming->snake($name),
			type: 'relation',
			nullable: false,
			hidden: false,
			fillable: true,
			cast: null
		);

		$f->relationKind = $kind;
		$f->targetEntity = $target;
		$f->isCollection = true;
		$f->inversedBy = $inversedBy !== null ? $this->naming->camel($inversedBy) : null;
		$f->isOwningSide = true;

		$resourceDef->fields[] = $f;
	}

	private function addInverseManyToManyField(ResourceDefinition $resourceDef, string $name, string $target, string $mappedBy): void
	{
		$f = new FieldDefinition(
			name: $this->naming->snake($name),
			type: 'relation',
			nullable: false,
			hidden: false,
			fillable: false,
			cast: null
		);

		$f->relationKind = 'ManyToMany';
		$f->targetEntity = $target;
		$f->isCollection = true;
		$f->mappedBy = $this->naming->camel($mappedBy);
		$f->isOwningSide = false;

		$resourceDef->fields[] = $f;
	}
}
