<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\FieldConstraints;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelFormRequestGenerator
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer,
		private Naming $names,
		private FieldConstraints $constraints
	) {
	}

	public function generate(GenerationContext $ctx): array
	{
		$tpl = $this->stubs->get('laravel/request');

		$rules = $this->rulesBlock($ctx);

		$content = $this->renderer->render($tpl, [
			'namespace' => 'App\\Http\\Requests\\'.$ctx->entity,
			'class' => $ctx->entity.'Request',
			'rules' => $rules,
		]);

		$path = $ctx->path('app/Http/Requests/'.$ctx->entity.'/'.$ctx->entity.'Request.php');
		$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

		return [['path' => $path, 'type' => 'request']];
	}

	private function rulesBlock(GenerationContext $ctx): string
	{
		$lines = [];
		$lines[] = '[';

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			$name = (string) $f->name;
			if ($name === 'id') {
				continue;
			}

			$field = $this->names->snake($name);
			$rules = $this->constraints->laravelRules($f, $field);

			$lines[] = "            '".$field."' => ['".implode("', '", $rules)."'],";
		}

		$lines[] = '        ]';

		return implode("\n", $lines);
	}
}
