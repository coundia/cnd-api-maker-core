<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class SymfonyFactoriesGenerator
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer,
		private Naming $names,
		private FieldTypeResolver $types
	) {
	}

	public function generate(GenerationContext $ctx): array
	{
		$targets = $this->factoryTargets($ctx);

		$files = [];
		foreach ($targets as $entity) {
			$tpl = $this->stubs->get('symfony/factory');
			$content = $this->renderer->render($tpl, [
				'entity' => $entity,
				'requiredDefaults' => $this->requiredDefaultsFor($ctx, $entity),
			]);

			$path = $ctx->path('src/Factory/'.$entity.'Factory.php');
			$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);
			$files[] = ['path' => $path, 'type' => 'factory'];
		}

		return $files;
	}

	private function factoryTargets(GenerationContext $ctx): array
	{
		$out = [$ctx->entity];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			if ($f->relationKind === null || $f->targetEntity === null) {
				continue;
			}

			if (!$f->fillable || $f->nullable) {
				continue;
			}

			$out[] = (string) $f->targetEntity;
		}

		return array_values(array_unique($out));
	}

	private function requiredDefaultsFor(GenerationContext $ctx, string $entity): string
	{
		if ($entity !== $ctx->entity) {
			return '';
		}

		$lines = [];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			if ((string) $f->name === 'id' || !$f->fillable || $f->nullable) {
				continue;
			}

			$prop = $this->names->camel((string) $f->name);

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				$target = (string) $f->targetEntity;
				$lines[] = "            '".$prop."' => \\App\\Factory\\{$target}Factory::new(),";
				continue;
			}

			if ($this->types->isDateLike($f)) {
				$lines[] = "            '".$prop."' => new \\DateTimeImmutable('2026-01-01'),";
				continue;
			}

			$t = strtolower((string) $f->type);

			if (in_array($t, ['bool', 'boolean'], true)) {
				$lines[] = "            '".$prop."' => true,";
				continue;
			}

			if (in_array($t, ['int', 'integer', 'bigint'], true)) {
				$lines[] = "            '".$prop."' => 123,";
				continue;
			}

			if (in_array($t, ['float', 'double', 'decimal'], true)) {
				$lines[] = "            '".$prop."' => 12.3,";
				continue;
			}

			$lines[] = "            '".$prop."' => 'VAL-'.self::faker()->uuid(),";
		}

		return implode("\n", $lines);
	}
}
