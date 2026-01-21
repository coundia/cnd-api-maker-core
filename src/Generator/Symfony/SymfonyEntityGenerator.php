<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\DoctrineColumnResolver;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class SymfonyEntityGenerator
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer,
		private Naming $names,
		private Naming $naming,
		private DoctrineColumnResolver $doctrine
	) {
	}

	public function generate(GenerationContext $ctx): array
	{
		$tpl = $this->stubs->get('symfony/entity');

		$snakePlural = $this->naming->pluralize($ctx->entitySnake);
		$table = $ctx->def->storage->table ?? $snakePlural;

		[$apiFilterUses, $apiFilterAttributes] = $this->apiFiltersBlock($ctx);

		$uriPrefix = '';

		$securityEnabled = (bool) ($ctx->def->features->security?? $ctx->def->security->enabled ?? false);
		$prefix = $ctx->def->security->prefix ?? 'APP_';
		$module = $ctx->def->security->moduleCode ?? strtoupper((string) $ctx->entitySnake);

		$auditEnabled = (bool) ($ctx->def->features->audit ?? false);
		$softDeletesEnabled = (bool) ($ctx->def->features->softDeletes ?? false);

		$sec = static function (bool $enabled, string $expr): string {
			return $enabled ? "	security: \"{$expr}\"," : '';
		};


		$content = $this->renderer->render($tpl, [
			'namespace' => 'App\\Entity',
			'entity' => $ctx->entity,
			'table' => $table,
			'auditTraitUse' => $auditEnabled ? "use App\\Entity\\Traits\\AuditTrait;" : '',
			'softDeletesTraitUse' => $softDeletesEnabled ? "use App\\Entity\\Traits\\SoftDeletesTrait;" : '',
			'auditTraitLine' => $auditEnabled ? "use AuditTrait;" : '',
			'softDeletesTraitLine' => $softDeletesEnabled ? "use SoftDeletesTrait;" : '',
			'extraUses' => $this->extraUses($ctx),
			'apiFilterUses' => $apiFilterUses,
			'apiFilterAttributes' => $apiFilterAttributes,
			'constructor' => $this->constructorBlock($ctx),
			'dtoInputFqn' => 'App\\Dto\\'.$ctx->entity.'\\'.$ctx->entity.'Input',
			'dtoOutputFqn' => 'App\\Dto\\'.$ctx->entity.'\\'.$ctx->entity.'Output',
			'collectionProviderFqn' => 'App\\State\\'.$ctx->entity.'\\'.$ctx->entity.'CollectionProvider',
			'itemProviderFqn' => 'App\\State\\'.$ctx->entity.'\\'.$ctx->entity.'ItemProvider',
			'writeProcessorFqn' => 'App\\State\\'.$ctx->entity.'\\'.$ctx->entity.'WriteProcessor',
			'deleteProcessorFqn' => 'App\\State\\'.$ctx->entity.'\\'.$ctx->entity.'DeleteProcessor',
			'repositoryFqn' => 'App\\Repository\\'.$ctx->entity.'Repository',
			'repository' => $ctx->entity.'Repository',
			'uriPrefix' => $uriPrefix,
			'groupsRead' => $ctx->groupsRead,
			'groupsWrite' => $ctx->groupsWrite,
			'opBase' => $ctx->opBase,
			'snakePlural' => $snakePlural,
			'idRequirement' => $ctx->idRequirement,
			'idProperty' => $this->idProperty($ctx),
			'properties' => $this->propertiesBlock($ctx),
			'securityLineList' => $sec($securityEnabled, "is_granted('".$prefix.$module.":LIST')"),
			'securityLineCreate' => $sec($securityEnabled, "is_granted('".$prefix.$module.":CREATE')"),
			'securityLineView' => $sec($securityEnabled, "is_granted('".$prefix.$module.":VIEW')"),
			'securityLineUpdate' => $sec($securityEnabled, "is_granted('".$prefix.$module.":UPDATE')"),
			'securityLineDelete' => $sec($securityEnabled, "is_granted('".$prefix.$module.":DELETE')"),
		]);

		$path = $ctx->path('src/Entity/'.$ctx->entity.'.php');
		$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

		return [['path' => $path, 'type' => 'entity']];
	}

	private function extraUses(GenerationContext $ctx): string
	{
		foreach ($ctx->def->fields as $f) {
			if ($f instanceof FieldDefinition && $f->relationKind !== null && $f->isCollection) {
				return implode("\n", [
					'use Doctrine\\Common\\Collections\\ArrayCollection;',
					'use Doctrine\\Common\\Collections\\Collection;',
				]);
			}
		}

		return '';
	}

	private function apiFiltersBlock(GenerationContext $ctx): array
	{
		$search = [];
		$order = [];
		$order['id'] = 'ASC';

		$hasBool = false;
		$hasDate = false;

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			$name = (string) $f->name;
			if ($name === 'id') {
				continue;
			}

			$prop = $this->names->camel($name);

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				if (!$f->isCollection) {
					$search[$prop] = 'exact';
					$order[$prop] = 'ASC';
				}
				continue;
			}

			$t = strtolower((string) $f->type);

			if (in_array($t, ['bool', 'boolean'], true)) {
				$hasBool = true;
				$search[$prop] = 'exact';
				$order[$prop] = 'ASC';
				continue;
			}

			if (in_array($t, ['date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localtime'], true)) {
				$hasDate = true;
				$search[$prop] = 'exact';
				$order[$prop] = 'ASC';
				continue;
			}

			if (in_array($t, ['int', 'integer', 'long', 'bigint', 'float', 'double', 'decimal', 'bigdecimal', 'duration'], true)) {
				$search[$prop] = 'exact';
				$order[$prop] = 'ASC';
				continue;
			}

			if ($prop === 'ordre' || $name === 'ordre') {
				$order[$prop] = 'ASC';
				$search[$prop] = 'exact';
				continue;
			}

			$search[$prop] = 'partial';
			$order[$prop] = 'ASC';
		}

		$uses = [
			'use ApiPlatform\\Metadata\\ApiFilter;',
			'use ApiPlatform\\Doctrine\\Orm\\Filter\\SearchFilter;',
			'use ApiPlatform\\Doctrine\\Orm\\Filter\\OrderFilter;',
		];

		if ($hasBool) {
			$uses[] = 'use ApiPlatform\\Doctrine\\Orm\\Filter\\BooleanFilter;';
		}
		if ($hasDate) {
			$uses[] = 'use ApiPlatform\\Doctrine\\Orm\\Filter\\DateFilter;';
		}

		$attrs = [];
		if ($search !== []) {
			$attrs[] = "#[ApiFilter(SearchFilter::class, properties: ".$this->exportAssocStringArray($search).")]";
		}

		$attrs[] = "#[ApiFilter(OrderFilter::class, properties: ".$this->exportAssocStringArray($order).", arguments: ['orderParameterName' => 'order'])]";

		if ($hasBool) {
			$boolProps = [];
			foreach ($ctx->def->fields as $f) {
				if ($f instanceof FieldDefinition && $f->relationKind === null) {
					$t = strtolower((string) $f->type);
					if (in_array($t, ['bool', 'boolean'], true)) {
						$boolProps[] = $this->names->camel((string) $f->name);
					}
				}
			}
			if ($boolProps !== []) {
				$attrs[] = "#[ApiFilter(BooleanFilter::class, properties: ".$this->exportList($boolProps).")]";
			}
		}

		if ($hasDate) {
			$dateProps = [];
			foreach ($ctx->def->fields as $f) {
				if ($f instanceof FieldDefinition && $f->relationKind === null) {
					$t = strtolower((string) $f->type);
					if (in_array($t, ['date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localtime'], true)) {
						$dateProps[] = $this->names->camel((string) $f->name);
					}
				}
			}
			if ($dateProps !== []) {
				$attrs[] = "#[ApiFilter(DateFilter::class, properties: ".$this->exportList($dateProps).")]";
			}
		}

		return [implode("\n", array_unique($uses)), implode("\n", $attrs)];
	}

	private function exportAssocStringArray(array $map): string
	{
		$items = [];
		foreach ($map as $k => $v) {
			$items[] = "'".$k."' => '".$v."'";
		}
		return '['.implode(', ', $items).']';
	}

	private function exportList(array $items): string
	{
		$out = array_map(static fn ($v) => "'".$v."'", $items);
		return '['.implode(', ', $out).']';
	}

	private function constructorBlock(GenerationContext $ctx): string
	{
		$inits = [];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}
			if ($f->relationKind === null || !$f->isCollection) {
				continue;
			}

			$prop = $this->names->camel($f->name);
			$inits[] = '        $this->'.$prop.' = new ArrayCollection();';
		}

		if ($inits === []) {
			return '';
		}

		return implode("\n", [
			'    public function __construct()',
			'    {',
			...$inits,
			'    }',
			'',
		]);
	}

	private function idProperty(GenerationContext $ctx): string
	{
		if ($ctx->def->api->uuid) {
			return implode("\n", [
				"    #[ORM\\Id]",
				"    #[ORM\\Column(type: 'uuid', unique: true)]",
				"    #[ORM\\GeneratedValue(strategy: 'CUSTOM')]",
				"    #[ORM\\CustomIdGenerator(class: 'doctrine.uuid_generator')]",
				"    #[Groups(['".$ctx->groupsRead."', '".$ctx->groupsWrite."'])]",
				"    public ?\\Symfony\\Component\\Uid\\Uuid \$id = null;",
				"",
			]);
		}

		return implode("\n", [
			"    #[ORM\\Id]",
			"    #[ORM\\GeneratedValue]",
			"    #[ORM\\Column(type: 'integer')]",
			"    #[Groups(['".$ctx->groupsRead."', '".$ctx->groupsWrite."'])]",
			"    public ?int \$id = null;",
			"",
		]);
	}

	private function propertiesBlock(GenerationContext $ctx): string
	{
		$parts = [];

		if ($ctx->def->features->tenant) {
			$parts[] = implode("\n", [
				"    #[ORM\\Column(type: 'uuid', nullable: true)]",
				"    public ?\\Symfony\\Component\\Uid\\Uuid \$tenantId = null;",
				"",
			]);
		}

		$parts[] = $this->fieldsBlock($ctx, $ctx->def->fields);

		if ($ctx->def->features->audit) {
			$parts[] = implode("\n", [
				"    #[ORM\\Column(type: 'string', length: 64, nullable: true)]",
				"    public ?string \$createdBy = null;",
				"",
				"    #[ORM\\Column(type: 'string', length: 64, nullable: true)]",
				"    public ?string \$updatedBy = null;",
				"",
			]);
		}

		if ($ctx->def->features->softDeletes) {
			$parts[] = implode("\n", [
				"    #[ORM\\Column(type: 'datetime_immutable', nullable: true)]",
				"    public ?\\DateTimeImmutable \$deletedAt = null;",
				"",
			]);
		}

		return implode("\n", array_filter($parts, static fn ($v) => $v !== ''));
	}

	private function relationBlock(GenerationContext $ctx, FieldDefinition $f): string
	{
		$prop = $this->names->camel($f->name);
		$target = $this->naming->studly($f->targetEntity);

		if ($f->relationKind === 'ManyToOne') {
			return implode("\n", [
				"    #[ORM\\ManyToOne(targetEntity: {$target}::class)]",
				"    #[ORM\\JoinColumn(nullable: ".($f->nullable ? 'true' : 'false').")]",
				"    public ?{$target} \${$prop} = null;",
			]);
		}

		if ($f->relationKind === 'OneToOne') {
			if ($f->isOwningSide) {
				return implode("\n", [
					"    #[ORM\\OneToOne(targetEntity: {$target}::class)]",
					"    #[ORM\\JoinColumn(nullable: ".($f->nullable ? 'true' : 'false').")]",
					"    public ?{$target} \${$prop} = null;",
				]);
			}

			$mappedBy = $f->mappedBy ?? '';
			return implode("\n", [
				"    #[ORM\\OneToOne(mappedBy: '{$mappedBy}', targetEntity: {$target}::class)]",
				"    public ?{$target} \${$prop} = null;",
			]);
		}

		if ($f->relationKind === 'OneToMany') {
			$mappedBy = $f->mappedBy ?? '';
			return implode("\n", [
				"    #[ORM\\OneToMany(mappedBy: '{$mappedBy}', targetEntity: {$target}::class)]",
				"    public Collection \${$prop};",
			]);
		}

		if ($f->relationKind === 'ManyToMany') {
			if ($f->isOwningSide) {
				$inversedBy = $f->inversedBy ?? '';
				return implode("\n", [
					"    #[ORM\\ManyToMany(targetEntity: {$target}::class, inversedBy: '{$inversedBy}')]",
					"    public Collection \${$prop};",
				]);
			}

			$mappedBy = $f->mappedBy ?? '';
			return implode("\n", [
				"    #[ORM\\ManyToMany(targetEntity: {$target}::class, mappedBy: '{$mappedBy}')]",
				"    public Collection \${$prop};",
			]);
		}

		return "    public ?{$target} \${$prop} = null;";
	}

	private function fieldsBlock(GenerationContext $ctx, array $fields): string
	{
		$lines = [];

		foreach ($fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				$lines[] = $this->relationBlock($ctx, $f);
				$lines[] = '';
				continue;
			}

			$prop = $this->names->camel($f->name);
			$col = (string) $f->name;

			$ormType = $this->doctrine->ormType($f);
			$nullable = $f->nullable ? 'true' : 'false';
			$unique = (property_exists($f, 'unique') && $f->unique) ? ', unique: true' : '';

			if ($ormType === 'string') {
				$len = (property_exists($f, 'maxLength') && $f->maxLength) ? (int) $f->maxLength : null;
				$lenPart = $len ? ', length: '.$len : '';
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'string'".$lenPart.", nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?string' : 'string')." \$".$prop." = ".($f->nullable ? 'null' : "''").";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'text') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'text', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?string' : 'string')." \$".$prop." = ".($f->nullable ? 'null' : "''").";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'uuid') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'uuid', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ?\\Symfony\\Component\\Uid\\Uuid \$".$prop." = null;";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'decimal') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'decimal', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?string' : 'string')." \$".$prop." = ".($f->nullable ? 'null' : "'0'").";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'blob') {
				//todo
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'string', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public mixed \$".$prop." = null;";
				$lines[] = '';
				continue;
			}

			if (in_array($ormType, ['date_immutable', 'datetime_immutable', 'time_immutable'], true)) {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: '".$ormType."', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ?\\DateTimeImmutable \$".$prop." = null;";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'boolean') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'boolean', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?bool' : 'bool')." \$".$prop." = ".($f->nullable ? 'null' : 'false').";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'integer') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'integer', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?int' : 'int')." \$".$prop." = ".($f->nullable ? 'null' : '0').";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'bigint') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'bigint', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?int' : 'int')." \$".$prop." = ".($f->nullable ? 'null' : '0').";";
				$lines[] = '';
				continue;
			}

			if ($ormType === 'float') {
				$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'float', nullable: ".$nullable.$unique.")]";
				$lines[] = "    public ".($f->nullable ? '?float' : 'float')." \$".$prop." = ".($f->nullable ? 'null' : '0.0').";";
				$lines[] = '';
				continue;
			}

			$lines[] = "    #[ORM\\Column(name: '".$col."', type: 'string', nullable: ".$nullable.$unique.")]";
			$lines[] = "    public ".($f->nullable ? '?string' : 'string')." \$".$prop." = ".($f->nullable ? 'null' : "''").";";
			$lines[] = '';
		}

		return implode("\n", $lines);
	}
}
