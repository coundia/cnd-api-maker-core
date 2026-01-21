<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;

final readonly class ApplyAssignmentsBuilder
{
	public function __construct(
		private Naming $names,
		private FieldTypeResolver $types
	) {
	}

	public function build(GenerationContext $ctx, bool $hasBase64): string
	{
		$lines = [];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			$prop = $this->names->camel((string) $f->name);

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				if (!$f->fillable) {
					continue;
				}

				if ($f->isCollection) {
					$lines[] = "        if (is_array(\$input->{$prop} ?? null)) {";
					$lines[] = "            \$entity->{$prop}->clear();";
					$lines[] = "            foreach (\$input->{$prop} as \$v) {";
					$lines[] = "                \$rel = \$resolver->resolve((string) \$v, '{$f->targetEntity}');";
					$lines[] = "                if (\$rel !== null) {";
					$lines[] = "                    \$entity->{$prop}->add(\$rel);";
					$lines[] = "                }";
					$lines[] = "            }";
					$lines[] = "        }";
					continue;
				}

				$lines[] = "        \$entity->{$prop} = \$resolver->resolveNullable(\$input->{$prop} ?? null, '{$f->targetEntity}');";
				continue;
			}

			if ($this->types->isDateLike($f)) {
				$lines[] = "        if (\$input->{$prop} !== null && \$input->{$prop} !== '') {";
				$lines[] = "            \$entity->{$prop} = new \\DateTimeImmutable((string) \$input->{$prop});";
				$lines[] = "        } else {";
				$lines[] = "            \$entity->{$prop} = null;";
				$lines[] = "        }";
				continue;
			}

			if ($hasBase64 && $this->isBase64Like($f)) {
				$dir = $ctx->entitySnake.'/'.$prop;
				$lines[] = "        if (\$input->{$prop} !== null && \$input->{$prop} !== '') {";
				$lines[] = "            \$stored = \$this->base64Files->store((string) \$input->{$prop}, '".$dir."');";
				$lines[] = "            \$entity->{$prop} = \$stored->storageKey;";
				$lines[] = "        }";
				continue;
			}

			$lines[] = "        \$entity->{$prop} = \$input->{$prop};";
		}

		return implode("\n", $lines);
	}

	private function isBase64Like(FieldDefinition $f): bool
	{
		$t = strtolower((string) $f->type);
		return in_array($t, ['blob', 'anyblob', 'imageblob', 'textblob'], true);
	}
}
