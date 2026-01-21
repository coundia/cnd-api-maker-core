<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlRelation
{
	public function __construct(
		public string $kind,
		public string $fromEntity,
		public ?string $fromField,
		public string $toEntity,
		public ?string $toField
	) {}
}
