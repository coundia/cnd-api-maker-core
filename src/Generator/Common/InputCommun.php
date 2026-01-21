<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Common;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class InputCommun
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer
	) {
	}

	/** @param list<FileSpec> $specs */
	public function generate(GenerationContext $ctx, array $specs, array $commonVars = []): array
	{
		$files = [];

		foreach ($specs as $spec) {
			$tpl = $this->stubs->get($spec->stub);

			$vars = array_merge($commonVars, $spec->vars, [
				'entity' => $ctx->entity,
				'entitySnake' => $ctx->entitySnake,
				'uriPrefix' => $ctx->uriPrefix,
				'groupsRead' => $ctx->groupsRead,
				'groupsWrite' => $ctx->groupsWrite,
				'opBase' => $ctx->opBase,
				'idRequirement' => $ctx->idRequirement,
			]);

			$content = $this->renderer->render($tpl, $vars);

			$resolvedPath = $this->renderPath($spec->path, $vars);
			$absPath = $ctx->path($resolvedPath);

			$this->writer->write($absPath, $content, $ctx->force, $ctx->dryRun);

			$files[] = ['path' => $absPath, 'type' => $spec->type];
		}

		return $files;
	}

	private function renderPath(string $path, array $vars): string
	{
		return preg_replace_callback('/\{\{(\w+)\}\}/', static function (array $m) use ($vars): string {
			$k = $m[1];
			return array_key_exists($k, $vars) ? (string) $vars[$k] : $m[0];
		}, $path) ?? $path;
	}
}
