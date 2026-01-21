<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Common;

use CndApiMaker\Core\Generator\Builders\DtoPropertiesBuilder;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class DtoGenerator
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer,
		private DtoPropertiesBuilder $props
	) {
	}

	public function generate(
		string $dtoBase,
		string $dtoNs,
		string $entity,
		array $fields,
		string $groupsRead,
		string $groupsWrite,
		bool $force,
		bool $dryRun,
		?GenerationContext $ctx = null
	): array {
		$tplIn = $this->stubs->get('common/dto.input');
		$tplOut = $this->stubs->get('common/dto.output');

		$inputContent = $this->renderer->render($tplIn, [
			'namespace' => $dtoNs,
			'entity' => $entity,
			'properties' => $this->props->input($fields, $groupsWrite),
		]);

		$outputContent = $this->renderer->render($tplOut, [
			'namespace' => $dtoNs,
			'entity' => $entity,
			'properties' => $this->props->output($fields, $groupsRead, $ctx),
		]);

		$inputPath = rtrim($dtoBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$entity.'Input.php';
		$outputPath = rtrim($dtoBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$entity.'Output.php';

		$this->writer->write($inputPath, $inputContent, $force, $dryRun);

        if($ctx->framework !="laravel"){
            $this->writer->write($outputPath, $outputContent, $force, $dryRun);
        }


		return [
			['path' => $inputPath, 'type' => 'dto'],
			['path' => $outputPath, 'type' => 'dto'],
		];
	}
}
