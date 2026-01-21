<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;

final class FieldTypeResolver
{
	public function isDateLike(FieldDefinition $f): bool
	{
		$t = strtolower((string) $f->type);
		$c = strtolower((string) ($f->cast ?? ''));

		return in_array($t, ['date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localtime'], true)
			|| in_array($c, ['date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localtime'], true);
	}

	public function inputPhpType(FieldDefinition $f): string
	{
		if ($f->relationKind !== null && $f->targetEntity !== null) {
			return $f->isCollection ? '?array' : '?string';
		}

		$t = strtolower((string) $f->type);

		return match ($t) {
			'bool', 'boolean' => '?bool',

			'int', 'integer' => '?int',

			'long', 'bigint' => '?int',

			'float', 'double' => '?float',

			'decimal', 'bigdecimal' => '?string',

			'uuid' => '?string',

			'date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'duration', 'localtime' => '?string',

			'blob', 'anyblob', 'imageblob', 'textblob' => '?string',

			'enum' => '?string',

			'string', 'text' => '?string',

			default => '?string',
		};
	}

	public function outputPhpType(FieldDefinition $f): string
	{
		if ($f->relationKind !== null && $f->targetEntity !== null) {
			$base = $f->isCollection ? 'array' : 'string';
			return $f->nullable ? '?'.$base : $base;
		}

		$t = strtolower((string) $f->type);

		$base = match ($t) {
			'bool', 'boolean' => 'bool',

			'int', 'integer' => 'int',

			'long', 'bigint' => 'int',

			'float', 'double' => 'float',

			'decimal', 'bigdecimal' => 'string',

			'uuid' => 'string',

			'date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'duration', 'localtime' => 'string',

			'blob', 'anyblob', 'imageblob', 'textblob' => 'string',

			'enum' => 'string',

			'string', 'text' => 'string',

			default => 'string',
		};

		return $f->nullable ? '?'.$base : $base;
	}

	public function phpEntityType(FieldDefinition $f, GenerationContext $ctx): string
	{
		if ($f->relationKind !== null && $f->targetEntity !== null) {
			return (string) $f->targetEntity;
		}

		$t = strtolower((string) $f->type);

		return match ($t) {
			'bool', 'boolean' => 'bool',

			'int', 'integer' => 'int',

			'long', 'bigint' => 'int',

			'float', 'double' => 'float',

			'decimal', 'bigdecimal' => 'string',

			'uuid' => '\\Symfony\\Component\\Uid\\Uuid',

			'date', 'localdate', 'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localtime' => '\\DateTimeImmutable',

			'duration' => 'string',

			'blob', 'anyblob', 'imageblob', 'textblob' => 'string',

			'enum' => 'string',

			'string', 'text' => 'string',

			default => 'string',
		};
	}
}
