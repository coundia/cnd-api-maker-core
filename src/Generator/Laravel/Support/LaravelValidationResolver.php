<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel\Support;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\Support\ValidationResolver;

final readonly class LaravelValidationResolver
{
	public function __construct(private ValidationResolver $rules)
	{
	}

	public function rules(FieldDefinition $f, string $table, string $column): array
	{
		return $this->rules->laravelRules($f, $table, $column);
	}
}
