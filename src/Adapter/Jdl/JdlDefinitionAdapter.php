<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

use CndApiMaker\Core\Adapter\DefinitionAdapter;

final class JdlDefinitionAdapter implements DefinitionAdapter
{
	private ?JdlConfig $lastConfig = null;

	public function __construct(
		private JdlParser $parser,
		private JdlToResourceMapper $mapper
	) {
	}

	public function fromFile(string $path): array
	{
		if (!is_file($path)) {
			throw new \RuntimeException('JDL file not found: '.$path);
		}

		return $this->fromString((string) file_get_contents($path));
	}

	public function fromString(string $content): array
	{
		$doc = $this->parser->parse($content);


		$this->lastConfig = $doc->config ?? null;

		return $this->mapper->map($doc);
	}

	public function getFramework(): string
	{
		return $this->lastConfig?->framework ?? 'symfony';
	}
}
