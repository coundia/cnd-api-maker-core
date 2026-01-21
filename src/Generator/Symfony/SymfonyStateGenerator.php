<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Symfony\State\SymfonyStateFilesWriter;
use CndApiMaker\Core\Generator\Symfony\State\SymfonyStatePlanBuilder;

final readonly class SymfonyStateGenerator
{
	public function __construct(
		private SymfonyStatePlanBuilder $planBuilder,
		private SymfonyStateFilesWriter $writer
	) {
	}

	public function generate(GenerationContext $ctx): array
	{
		$plan = $this->planBuilder->build($ctx);

		return $this->writer->writeAll($ctx, $plan);
	}
}
