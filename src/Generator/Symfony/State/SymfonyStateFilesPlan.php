<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

final class SymfonyStateFilesPlan
{
	public function __construct(
		public string $repoTpl,
		public array $repoVars,
		public string $repoPath,

		public string $mapperTpl,
		public array $mapperVars,
		public string $mapperPath,

		public string $payloadTpl,
		public array $payloadVars,
		public string $payloadPath,

		public string $collectionTpl,
		public array $collectionVars,
		public string $collectionPath,

		public string $itemTpl,
		public array $itemVars,
		public string $itemPath,

		public string $writeTpl,
		public array $writeVars,
		public string $writePath,

		public string $deleteTpl,
		public array $deleteVars,
		public string $deletePath,
	) {
	}
}
