<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlField
{
	public function __construct(
		public string $name,
		public string $type,
		public bool $required = false
	) {}
}
