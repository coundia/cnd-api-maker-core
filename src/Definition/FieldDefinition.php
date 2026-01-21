<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

final class FieldDefinition
{
	public ?string $relationKind = null;
	public ?string $targetEntity = null;
	public bool $isCollection = false;
	public ?string $mappedBy = null;
	public ?string $inversedBy = null;
	public bool $isOwningSide = false;

	public function __construct(
		public string $name,
		public string $type,
		public bool $nullable = false,
		public bool $hidden = false,
		public bool $fillable = true,
		public ?string $cast = null
	) {}
}
