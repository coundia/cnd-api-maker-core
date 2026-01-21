<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlConfig
{
	public function __construct(
		public string $framework = 'symfony',
		public string $driver = 'doctrine',
//		public string $uriPrefix = '/api/v1',
		public string $uriPrefix = '/v1',
		public bool $uuid = true,
		public bool $tenant = false,
		public bool $softDeletes = false,
		public bool $audit = false,
		public bool $factory = false
	) {}
}
