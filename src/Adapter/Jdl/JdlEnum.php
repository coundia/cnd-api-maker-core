<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlEnum
{
	/** @param string[] $values */
	public function __construct(
		public string $name,
		public array $values
	) {}
}
