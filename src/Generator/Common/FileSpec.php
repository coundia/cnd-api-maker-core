<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Common;

final readonly class FileSpec
{
	public function __construct(
		public string $stub,
		public string $path,
		public string $type,
		public array $vars = []
	) {
	}
}
