<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter;

use CndApiMaker\Core\Definition\ResourceDefinition;

interface DefinitionAdapter
{
	/** @return ResourceDefinition[] */
	public function fromFile(string $path): array;

	/** @return ResourceDefinition[] */
	public function fromString(string $content): array;

	public function getFramework();
}
