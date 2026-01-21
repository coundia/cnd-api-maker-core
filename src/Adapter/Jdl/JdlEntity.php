<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlEntity
{
	/** @param JdlField[] $fields */
	public function __construct(
		public string $name,
		public array $fields
	) {}
}
