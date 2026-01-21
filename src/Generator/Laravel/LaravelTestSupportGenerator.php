<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelTestSupportGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $tpl = $this->stubs->get('laravel/tests.support.base_api_test_case');

        $content = $this->renderer->render($tpl, []);

        $path = $ctx->path('tests/Support/BaseApiTestCase.php');
        $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

        return [
            ['path' => $path, 'type' => 'test-support'],
        ];
    }
}
