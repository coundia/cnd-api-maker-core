<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Laravel;

use CndApiMaker\Core\Generator\Builders\LaravelMigrationColumnsBuilder;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class LaravelMigrationGenerator
{
    public function __construct(
        private StubRepository $stubs,
        private TemplateRenderer $renderer,
        private FileWriter $writer,
        private LaravelMigrationColumnsBuilder $columnsBuilder,
        private Naming $naming
    ) {
    }

    public function generate(GenerationContext $ctx): array
    {
        $tpl = $this->stubs->get('laravel/migration.create_table');

        $table = $this->resolveTable($ctx);
        $columns = $this->columnsBuilder->columns($ctx->def);
        $hasTenant = (bool) (($ctx->def->features->tenant ?? true));

        $content = $this->renderer->render($tpl, [
            'table' => $table,
            'columns' => $columns,
            'hasTenant' => $hasTenant,
        ]);

        $dir = $ctx->path('database/migrations');
        $base = '01_01_100000_cnd_create_'.$table.'_table.php';

        $existing = $this->findExistingCreateMigration($dir, $base);

        if ($existing !== null) {
            $path = $existing;
        } else {
            $seq = $this->nextSequence($dir);
            $path = $dir.'/'.$seq.'_'.$base;
        }

        $this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

        return [
            ['path' => $path, 'type' => 'migration'],
        ];
    }

    private function resolveTable(GenerationContext $ctx): string
    {
        $def = $ctx->def;

        if (is_object($def) && property_exists($def, 'storage') && is_object($def->storage) && property_exists($def->storage, 'table')) {
            $t = (string) ($def->storage->table ?? '');
            if ($t !== '') {
                return $t;
            }
        }

        return $this->naming->plural($ctx->entitySnake);
    }

    private function findExistingCreateMigration(string $dir, string $base): ?string
    {
        $files = glob($dir.'/*_'.$base) ?: [];
        sort($files);
        return $files[0] ?? null;
    }

    private function nextSequence(string $dir): string
    {
        $files = glob($dir.'/*.php') ?: [];
        $max = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^(\d{4})_/', $name, $m) === 1) {
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        $next = $max + 1;
        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
