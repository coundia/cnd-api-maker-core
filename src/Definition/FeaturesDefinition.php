<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

use CndApiMaker\Core\Generator\GenerationContext;

final class FeaturesDefinition
{
	private array $items = [];

	public function __construct(array $items = [])
	{
		$this->items = $this->defaults();

		foreach ($items as $k => $v) {
			if (!is_string($k) || $k === '') {
				continue;
			}
			$this->items[$k] = (bool) $v;
		}
	}

	public function enabled(string $key): bool
	{
		return (bool) ($this->{$key} ?? $this->items[$key] ?? false);
	}

	public function set(string $key, bool $value): void
	{
		$this->items[$key] = $value;
	}

	public function all(): array
	{
		return $this->items;
	}


	public function __get(string $name): bool
	{
		return $this->enabled($name);
	}

	private function defaults(): array
	{
		return [
			'commun' => true,
			'dto' => true,
			'factories' => true,
			'entity' => true,
			'state' => true,
			'tests' => true,
			'factory' => true,

			'security' => true,
			'tenant' => true,
			'softDeletes' => true,
			'audit' => true,
			'import' => true,
			'export' => true,
			'qa' => true,

		];
	}

    public static function isEnabled(GenerationContext $ctx, string $name): bool
    {
        $features = $ctx->def->features ?? null;

        if (is_object($features) && method_exists($features, 'enabled')) {
            return (bool) $features->enabled($name);
        }

        return false;
    }
}
