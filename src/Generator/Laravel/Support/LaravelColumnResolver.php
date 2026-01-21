<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class LaravelColumnResolver
{
	public function migrationColumn(FieldDefinition $f): string
	{
		$t = strtolower((string) $f->type);

		return match ($t) {
			'string' => 'string',
			'textblob' => 'text',
			'bool', 'boolean' => 'boolean',
			'int', 'integer' => 'integer',
			'long', 'bigint', 'duration' => 'bigInteger',
			'float' => 'float',
			'double' => 'double',
			'decimal', 'bigdecimal' => 'decimal',
			'enum' => 'string',
			'uuid' => 'uuid',
			'date', 'localdate' => 'date',
			'localtime' => 'time',
			'datetime', 'timestamp', 'zoneddatetime', 'instant' => 'dateTime',
			'blob', 'anyblob', 'imageblob' => 'binary',
			default => 'string',
		};
	}

    public function cast(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $t = strtolower(trim($type));

        return match ($t) {
            'string' => 'string',
            'text' => 'string',
            'bool', 'boolean' => 'boolean',
            'int', 'integer' => 'integer',
            'long', 'bigint' => 'integer',
            'float', 'double', 'decimal', 'bigdecimal' => 'float',
            'date', 'localdate' => 'date',
            'datetime', 'timestamp', 'zoneddatetime', 'instant', 'localdatetime' => 'datetime',
            'json', 'jsonb' => 'array',
            'uuid' => 'string',
            'blob', 'anyblob', 'imageblob', 'binary' => null,
            default => null,
        };
    }
}
