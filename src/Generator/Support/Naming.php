<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use Doctrine\Inflector\InflectorFactory;

final class Naming
{
    private $inflector;

    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function plural(string $word): string
    {
        return $this->inflector->pluralize($word);
    }

    public function snake(string $word): string
    {
        $word = preg_replace('/(?<!^)[A-Z]/', '_$0', $word) ?? $word;
        return strtolower($word);
    }

    public function studly(string $word): string
    {
        $word = str_replace(['-', '_'], ' ', $word);
        $word = ucwords($word);
        return str_replace(' ', '', $word);
    }

    public function pluralize(string $snake): string{
        return $this->inflector->pluralize($snake);
    }

	public function camel(string $word): string
	{
		$word = trim($word);
		if ($word === '') {
			return '';
		}

		$word = str_replace(['-', '_'], ' ', $word);
		$word = ucwords($word);
		$word = str_replace(' ', '', $word);

		return lcfirst($word);
	}
}
