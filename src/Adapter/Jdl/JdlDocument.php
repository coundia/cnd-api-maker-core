<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlDocument
{
	/** @param array<string, JdlEntity> $entities */
	public function __construct(
		public JdlConfig $config,
		public array $entities,
		/** @param array<string, JdlEnum> $enums */
		public array $enums,
		/** @param JdlRelation[] $relations */
		public array $relations
	) {}
}
