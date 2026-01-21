<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class LaravelCastResolver
{
	public function cast(?String $type): ?string
	{
        if ($type === null) {
            return null;
        }
		$t = strtolower($type);

		return match ($t) {
			'bool', 'boolean' => 'boolean',
			'int', 'integer' => 'integer',
			'long', 'bigint' => 'integer',
			'float', 'double' => 'float',
			'decimal', 'bigdecimal' => 'decimal:2',
			'date', 'localdate' => 'date:Y-m-d',
			'datetime', 'timestamp', 'zoneddatetime', 'instant' => 'datetime',
			'uuid' => 'string',
			default => null,
		};
	}
}
