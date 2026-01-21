<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class DoctrineColumnResolver
{
	public function ormType(FieldDefinition $f): string
	{
		if ($f->relationKind !== null && $f->targetEntity !== null) {
			return 'relation';
		}

		$t = strtolower((string) $f->type);
		$c = strtolower((string) ($f->cast ?? ''));

		$base = $c !== '' ? $c : $t;

		return match ($base) {
			'bool', 'boolean' => 'boolean',

			'int', 'integer' => 'integer',
			'long', 'bigint' => 'bigint',
			'duration' => 'bigint',

			'float', 'double' => 'float',
			'bigdecimal', 'decimal' => 'decimal',

			'uuid' => 'uuid',

			'localdate', 'date' => 'date_immutable',
			'instant', 'zoneddatetime', 'datetime', 'timestamp' => 'datetime_immutable',
			'localtime', 'time' => 'time_immutable',

			'textblob' => 'text',
			'blob', 'anyblob', 'imageblob' => 'blob',

			'enum' => 'string',

			default => 'string',
		};
	}
}
