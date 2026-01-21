<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class ValidationResolver
{
	public function symfonyAssertAttributes(FieldDefinition $f, string $groupsWrite): string
	{
		$lines = [];

		$name = (string) $f->name;
		if ($name === 'id') {
			return '';
		}

		if (!$f->nullable) {
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\NotBlank(groups: ['".$groupsWrite."'])]";
		}

		$t = strtolower((string) $f->type);

		if (in_array($t, ['uuid'], true)) {
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Uuid(groups: ['".$groupsWrite."'])]";
		}

		if (in_array($t, ['date', 'localdate'], true)) {
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Date(groups: ['".$groupsWrite."'])]";
		}

		if (in_array($t, ['datetime', 'timestamp', 'zoneddatetime', 'instant'], true)) {
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\DateTime(groups: ['".$groupsWrite."'])]";
		}

		if ($t === 'localtime') {
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Regex(pattern: '/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/', groups: ['".$groupsWrite."'])]";
		}

		if (property_exists($f, 'minLength') || property_exists($f, 'maxLength')) {
			$min = property_exists($f, 'minLength') ? (int) ($f->minLength ?? 0) : 0;
			$max = property_exists($f, 'maxLength') ? (int) ($f->maxLength ?? 0) : 0;
			if ($min > 0 || $max > 0) {
				$args = [];
				if ($min > 0) {
					$args[] = 'min: '.$min;
				}
				if ($max > 0) {
					$args[] = 'max: '.$max;
				}
				$args[] = "groups: ['".$groupsWrite."']";
				$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Length(".implode(', ', $args).")]";
			}
		}

		if (property_exists($f, 'pattern') && is_string($f->pattern) && $f->pattern !== '') {
			$pattern = str_replace("'", "\\'", $f->pattern);
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Regex(pattern: '".$pattern."', groups: ['".$groupsWrite."'])]";
		}

		if (property_exists($f, 'min') || property_exists($f, 'max')) {
			$min = $f->min ?? null;
			$max = $f->max ?? null;

			if ($min !== null || $max !== null) {
				$args = [];
				if ($min !== null) {
					$args[] = 'min: '.$this->scalar($min);
				}
				if ($max !== null) {
					$args[] = 'max: '.$this->scalar($max);
				}
				$args[] = "groups: ['".$groupsWrite."']";
				$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Range(".implode(', ', $args).")]";
			}
		}

		if ($t === 'enum' && property_exists($f, 'enumValues') && is_array($f->enumValues) && $f->enumValues !== []) {
			$choices = array_map(static fn ($v) => "'".str_replace("'", "\\'", (string) $v)."'", $f->enumValues);
			$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Choice(choices: [".implode(', ', $choices)."], groups: ['".$groupsWrite."'])]";
		}

		if (in_array($t, ['blob', 'anyblob', 'imageblob'], true)) {
			$minBytes = property_exists($f, 'minBytes') ? (int) ($f->minBytes ?? 0) : 0;
			$maxBytes = property_exists($f, 'maxBytes') ? (int) ($f->maxBytes ?? 0) : 0;

			if ($t === 'imageblob') {
				$args = [];
				if ($minBytes > 0) {
					$args[] = 'minSize: '.$minBytes;
				}
				if ($maxBytes > 0) {
					$args[] = 'maxSize: '.$maxBytes;
				}
				$args[] = "groups: ['".$groupsWrite."']";
				$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\Image(".implode(', ', $args).")]";
			} else {
				$args = [];
				if ($minBytes > 0) {
					$args[] = 'minSize: '.$minBytes;
				}
				if ($maxBytes > 0) {
					$args[] = 'maxSize: '.$maxBytes;
				}
				$args[] = "groups: ['".$groupsWrite."']";
				$lines[] = "    #[\\Symfony\\Component\\Validator\\Constraints\\File(".implode(', ', $args).")]";
			}
		}

		return implode("\n", $lines);
	}

	public function laravelRules(FieldDefinition $f, string $table, string $column): array
	{
		$rules = [];

		$name = (string) $f->name;
		if ($name === 'id') {
			return [];
		}

		$rules[] = $f->nullable ? 'nullable' : 'required';

		$t = strtolower((string) $f->type);

		if (in_array($t, ['string', 'textblob', 'enum'], true)) {
			$rules[] = 'string';
		}

		if (in_array($t, ['int', 'integer', 'long', 'bigint', 'duration'], true)) {
			$rules[] = 'integer';
		}

		if (in_array($t, ['float', 'double', 'decimal', 'bigdecimal'], true)) {
			$rules[] = 'numeric';
		}

		if (in_array($t, ['bool', 'boolean'], true)) {
			$rules[] = 'boolean';
		}

		if ($t === 'uuid') {
			$rules[] = 'uuid';
		}

		if (in_array($t, ['date', 'localdate'], true)) {
			$rules[] = 'date_format:Y-m-d';
		}

		if (in_array($t, ['datetime', 'timestamp', 'zoneddatetime', 'instant'], true)) {
			$rules[] = 'date';
		}

		if ($t === 'localtime') {
			$rules[] = 'date_format:H:i:s';
		}

		if (property_exists($f, 'minLength') && $f->minLength !== null) {
			$rules[] = 'min:'.(int) $f->minLength;
		}

		if (property_exists($f, 'maxLength') && $f->maxLength !== null) {
			$rules[] = 'max:'.(int) $f->maxLength;
		}

		if (property_exists($f, 'pattern') && is_string($f->pattern) && $f->pattern !== '') {
			$rules[] = 'regex:'.$f->pattern;
		}

		if (property_exists($f, 'min') && $f->min !== null) {
			$rules[] = 'min:'.$this->scalar($f->min);
		}

		if (property_exists($f, 'max') && $f->max !== null) {
			$rules[] = 'max:'.$this->scalar($f->max);
		}

		if (in_array($t, ['blob', 'anyblob', 'imageblob'], true)) {
			$rules[] = 'file';

			$minBytes = property_exists($f, 'minBytes') ? (int) ($f->minBytes ?? 0) : 0;
			$maxBytes = property_exists($f, 'maxBytes') ? (int) ($f->maxBytes ?? 0) : 0;

			if ($t === 'imageblob') {
				$rules[] = 'image';
			}

			if ($minBytes > 0) {
				$rules[] = 'min:'.(int) ceil($minBytes / 1024);
			}
			if ($maxBytes > 0) {
				$rules[] = 'max:'.(int) floor($maxBytes / 1024);
			}
		}

		if (property_exists($f, 'unique') && $f->unique) {
			$rules[] = 'unique:'.$table.','.$column;
		}

		return $rules;
	}

	private function scalar(mixed $v): string
	{
		if (is_int($v) || is_float($v)) {
			return (string) $v;
		}
		return (string) $v;
	}
}
