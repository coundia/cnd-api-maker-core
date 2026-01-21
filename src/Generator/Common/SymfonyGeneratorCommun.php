<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Common;

use CndApiMaker\Core\Definition\FeaturesDefinition;
use CndApiMaker\Core\Generator\GenerationContext;

final readonly class SymfonyGeneratorCommun
{
	public function __construct(private InputCommun $gen)
	{
	}

	public function generate(GenerationContext $ctx): array
	{
		$entity = $ctx->entity;

		$features = $ctx->def->features;

		$auditEnabled = $features instanceof FeaturesDefinition
			? $features->enabled('audit')
			: (bool) ($features->audit ?? false);

		$softDeletesEnabled = $features instanceof FeaturesDefinition
			? $features->enabled('softDeletes')
			: (bool) ($features->softDeletes ?? false);

		$communEnabled = $features instanceof FeaturesDefinition
			? $features->enabled('commun')
			: (bool) ($features->commun ?? true);

		if (!$communEnabled) {
		 	return [];
		}

		$specs = [];

		$specs[] = new FileSpec('symfony/Base64FileService', 'src/Service/Base64FileService.php', 'Service');

		if ($auditEnabled) {
			$specs[] = new FileSpec('symfony/AuditTrait', 'src/Entity/Traits/AuditTrait.php', 'trait');
		}

		if ($softDeletesEnabled) {
			$specs[] = new FileSpec('symfony/SoftDeletesTrait', 'src/Entity/Traits/SoftDeletesTrait.php', 'trait');
		}

		return $this->gen->generate($ctx, $specs, ['entity' => $entity]);
	}
}
