<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class SymfonyStateFilesWriter
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer
	) {
	}

	public function writeAll(GenerationContext $ctx, SymfonyStateFilesPlan $plan): array
	{
		$files = [];

		$files[] = $this->writeOne($ctx, $plan->repoTpl, $plan->repoVars, $plan->repoPath, 'repository');
		$files[] = $this->writeOne($ctx, $plan->mapperTpl, $plan->mapperVars, $plan->mapperPath, 'state');
		$files[] = $this->writeOne($ctx, $plan->payloadTpl, $plan->payloadVars, $plan->payloadPath, 'state');
		$files[] = $this->writeOne($ctx, $plan->collectionTpl, $plan->collectionVars, $plan->collectionPath, 'state');
		$files[] = $this->writeOne($ctx, $plan->itemTpl, $plan->itemVars, $plan->itemPath, 'state');
		$files[] = $this->writeOne($ctx, $plan->writeTpl, $plan->writeVars, $plan->writePath, 'state');
		$files[] = $this->writeOne($ctx, $plan->deleteTpl, $plan->deleteVars, $plan->deletePath, 'state');

		return $files;
	}

	private function writeOne(GenerationContext $ctx, string $tplKey, array $vars, string $path, string $type): array
	{
		$tpl = $this->stubs->get($tplKey);
		$content = $this->renderer->render($tpl, $vars);
		$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

		return ['path' => $path, 'type' => $type];
	}
}
