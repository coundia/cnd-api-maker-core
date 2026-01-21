<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;

final readonly class MapperAssignmentsBuilder
{
	public function __construct(
		private Naming $names,
		private FieldTypeResolver $types
	) {
	}

	public function build(GenerationContext $ctx): string
	{
		$lines = [];

		if ($ctx->def->api->uuid) {
			$lines[] = "        \$dto->id = \$entity->id?->toRfc4122();";
		} else {
			$lines[] = "        \$dto->id = \$entity->id;";
		}

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			$prop = $this->names->camel((string) $f->name);

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				if ($f->isCollection) {
					$lines[] = "        \$dto->{$prop} = [];";
					$lines[] = "        foreach (\$entity->{$prop} as \$rel) {";
					if ($ctx->def->api->uuid) {
						$lines[] = "            \$dto->{$prop}[] = \$rel->id?->toRfc4122();";
					} else {
						$lines[] = "            \$dto->{$prop}[] = \$rel->id;";
					}
					$lines[] = "        }";
					continue;
				}

				if ($ctx->def->api->uuid) {
					$lines[] = "        \$dto->{$prop} = \$entity->{$prop}?->id?->toRfc4122();";
				} else {
					$lines[] = "        \$dto->{$prop} = \$entity->{$prop}?->id;";
				}
				continue;
			}

			if ($this->types->isDateLike($f)) {
				$lines[] = "        \$dto->{$prop} = \$entity->{$prop}?->format('c');";
				continue;
			}

			$lines[] = "        \$dto->{$prop} = \$entity->{$prop};";
		}

		if ($ctx->def->features->tenant) {
			$lines[] = "        \$dto->tenantId = \$entity->tenantId?->toRfc4122();";
		}

		if ($ctx->def->features->audit) {
			$lines[] = "        \$dto->createdBy = \$entity->createdBy;";
			$lines[] = "        \$dto->updatedBy = \$entity->updatedBy;";
		}

		if ($ctx->def->features->softDeletes) {
			$lines[] = "        \$dto->deletedAt = \$entity->deletedAt?->format('c');";
		}

		return implode("\n", $lines);
	}
}
