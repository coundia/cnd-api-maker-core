<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

final class Base64Feature
{
	public function __construct(
		public bool $enabled,
		public string $uses,
		public string $ctorArg,
		public string $applyArg,
	) {
	}
}
