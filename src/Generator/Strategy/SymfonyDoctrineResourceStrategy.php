<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Strategy;

use CndApiMaker\Core\Generator\Common\DtoGenerator;
use CndApiMaker\Core\Generator\Common\SymfonyGeneratorCommun;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\GenerationResult;
use CndApiMaker\Core\Generator\Symfony\SymfonyEntityGenerator;
use CndApiMaker\Core\Generator\Symfony\SymfonyFactoriesGenerator;
use CndApiMaker\Core\Generator\Symfony\SymfonyStateGenerator;
use CndApiMaker\Core\Generator\Symfony\SymfonyTestsGenerator;

final readonly class SymfonyDoctrineResourceStrategy implements ResourceGenerationStrategy
{
	public function __construct(
		private DtoGenerator $dto,
		private SymfonyEntityGenerator $entity,
		private SymfonyStateGenerator $state,
		private SymfonyTestsGenerator $tests,
		private SymfonyFactoriesGenerator $factories,
		private SymfonyGeneratorCommun $commun,
	) {
	}

	public function supports(GenerationContext $ctx): bool
	{
		return $ctx->framework === 'symfony' && in_array($ctx->def->driver, ['doctrine', 'orm'], true);
	}

	public function generate(GenerationContext $ctx): GenerationResult
	{
		$files = [];

		$features = $ctx->def->features ?? (object) [];
		$genDto = (bool) ($features->dto ?? true);
		$genCommun = (bool) ($features->commun ?? true);
		$genFactories = (bool) ($features->factories ?? true);
		$genEntity = (bool) ($features->entity ?? true);
		$genState = (bool) ($features->state ?? true);
		$genTests = (bool) ($features->tests ?? ($ctx->def->tests->enabled ?? false));

		if ($genCommun) {
			$files = array_merge($files, $this->commun->generate($ctx));
		}

		if ($genDto) {
			$dtoBase = $ctx->path('src/Dto/'.$ctx->entity);
			$dtoNs = 'App\\Dto\\'.$ctx->entity;

			$files = array_merge(
				$files,
				$this->dto->generate(
				$dtoBase,
				$dtoNs,
				$ctx->entity,
				$ctx->def->fields,
				$ctx->groupsRead,
				$ctx->groupsWrite,
				$ctx->force,
				$ctx->dryRun,
				$ctx
			)
			);
		}

		if ($genFactories) {
			$files = array_merge($files, $this->factories->generate($ctx));
		}

		if ($genEntity) {
			$files = array_merge($files, $this->entity->generate($ctx));
		}

		if ($genState) {
			$files = array_merge($files, $this->state->generate($ctx));
		}

		if ($genTests) {
			$files = array_merge($files, $this->tests->generate($ctx));
		}

		return new GenerationResult($files);
	}
}
