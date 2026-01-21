<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Generator\GenerationContext;

trait GeneratorRenderHelpers
{
	protected function storageOrNull(GenerationContext $ctx): ?object
	{
		$def = $ctx->def;

		if (!is_object($def) || !property_exists($def, 'storage') || !is_object($def->storage)) {
			return null;
		}

		return $def->storage;
	}

	protected function renderStringList(array $items, int $indent = 8): string
	{
		if ($items === []) {
			return '';
		}

		$pad = str_repeat(' ', $indent);
		$out = [];

		foreach ($items as $it) {
			$out[] = $pad."'".(string) $it."',";
		}

		return implode("\n", $out);
	}

	protected function renderKeyValueList(array $items, int $indent = 8): string
	{
		if ($items === []) {
			return '';
		}

		$pad = str_repeat(' ', $indent);
		$out = [];

		foreach ($items as $k => $v) {
			$out[] = $pad."'".(string) $k."' => '".(string) $v."',";
		}

		return implode("\n", $out);
	}

	protected function exportAssocStringArray(array $map): string
	{
		$items = [];

		foreach ($map as $k => $v) {
			$items[] = "'".$k."' => '".$v."'";
		}

		return '['.implode(', ', $items).']';
	}

	protected function exportList(array $items): string
	{
		$out = array_map(static fn ($v) => "'".$v."'", $items);

		return '['.implode(', ', $out).']';
	}
}
