<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Support;

use CndApiMaker\Core\Definition\FieldDefinition;

final class FieldConstraints
{
	public function symfonyAssertAttributes(FieldDefinition $f): array
	{
		$out = [];

		$required = !$f->nullable;

		if ($required) {
			$out[] = '#[Assert\\NotNull]';
			$t = strtolower((string) $f->type);
			if (in_array($t, ['string', 'textblob', 'enum', 'uuid'], true)) {
				$out[] = '#[Assert\\NotBlank]';
			}
		}

		$t = strtolower((string) $f->type);

		if (property_exists($f, 'minLength') || property_exists($f, 'maxLength')) {
			$min = (property_exists($f, 'minLength') && $f->minLength) ? (int) $f->minLength : null;
			$max = (property_exists($f, 'maxLength') && $f->maxLength) ? (int) $f->maxLength : null;

			if ($min !== null || $max !== null) {
				$args = [];
				if ($min !== null) $args[] = 'min: '.$min;
				if ($max !== null) $args[] = 'max: '.$max;
				$out[] = '#[Assert\\Length('.implode(', ', $args).')]';
			}
		}

		if (property_exists($f, 'pattern') && is_string($f->pattern) && $f->pattern !== '') {
			$out[] = '#[Assert\\Regex(pattern: \''.addslashes($f->pattern).'\')]';
		}

		if (in_array($t, ['int', 'integer', 'long', 'bigint', 'float', 'double', 'decimal', 'bigdecimal'], true)) {
			$min = (property_exists($f, 'min') && $f->min !== null) ? $f->min : null;
			$max = (property_exists($f, 'max') && $f->max !== null) ? $f->max : null;

			if ($min !== null || $max !== null) {
				$args = [];
				if ($min !== null) $args[] = 'min: '.(is_numeric($min) ? $min : '\''.(string) $min.'\'');
				if ($max !== null) $args[] = 'max: '.(is_numeric($max) ? $max : '\''.(string) $max.'\'');
				$out[] = '#[Assert\\Range('.implode(', ', $args).')]';
			}
		}

		if ($t === 'uuid') {
			$out[] = '#[Assert\\Uuid]';
		}

		if ($t === 'enum' && property_exists($f, 'enumValues') && is_array($f->enumValues) && $f->enumValues !== []) {
			$vals = array_map(static fn ($v) => '\''.addslashes((string) $v).'\'', $f->enumValues);
			$out[] = '#[Assert\\Choice(choices: ['.implode(', ', $vals).'])]';
		}

		if (in_array($t, ['blob', 'anyblob', 'imageblob'], true)) {
			$minB = (property_exists($f, 'minBytes') && $f->minBytes) ? (int) $f->minBytes : null;
			$maxB = (property_exists($f, 'maxBytes') && $f->maxBytes) ? (int) $f->maxBytes : null;

			if ($minB !== null || $maxB !== null) {
				$args = [];
				if ($minB !== null) $args[] = 'min: '.$minB;
				if ($maxB !== null) $args[] = 'max: '.$maxB;
				$out[] = '#[Assert\\Length('.implode(', ', $args).')]';
			}
		}

		return $out;
	}

	public function laravelRules(FieldDefinition $f, string $fieldName): array
	{
		$rules = [];

		$rules[] = $f->nullable ? 'nullable' : 'required';

		if ($f->relationKind !== null && $f->targetEntity !== null) {
			if ($f->isCollection) {
				$rules[] = 'array';
			} else {
				$rules[] = 'string';
			}
			return $rules;
		}

		$t = strtolower((string) $f->type);

		if (in_array($t, ['string', 'textblob', 'enum', 'duration', 'localtime'], true)) {
			$rules[] = 'string';
		}

		if (in_array($t, ['int', 'integer', 'long', 'bigint'], true)) {
			$rules[] = 'integer';
		}

		if (in_array($t, ['float', 'double'], true)) {
			$rules[] = 'numeric';
		}

		if (in_array($t, ['decimal', 'bigdecimal'], true)) {
			$rules[] = 'numeric';
		}

		if ($t === 'boolean') {
			$rules[] = 'boolean';
		}

		if (in_array($t, ['date', 'localdate'], true)) {
			$rules[] = 'date';
		}

		if (in_array($t, ['datetime', 'timestamp', 'zoneddatetime', 'instant'], true)) {
			$rules[] = 'date';
		}

		if ($t === 'uuid') {
			$rules[] = 'uuid';
		}

		if (property_exists($f, 'minLength') && $f->minLength) {
			$rules[] = 'min:'.(int) $f->minLength;
		}
		if (property_exists($f, 'maxLength') && $f->maxLength) {
			$rules[] = 'max:'.(int) $f->maxLength;
		}

		if (property_exists($f, 'pattern') && is_string($f->pattern) && $f->pattern !== '') {
			$rules[] = 'regex:/'.trim($f->pattern, '/').'/';
		}

		if (property_exists($f, 'min') && $f->min !== null) {
			$rules[] = 'min:'.$f->min;
		}
		if (property_exists($f, 'max') && $f->max !== null) {
			$rules[] = 'max:'.$f->max;
		}

		if ($t === 'enum' && property_exists($f, 'enumValues') && is_array($f->enumValues) && $f->enumValues !== []) {
			$rules[] = 'in:'.implode(',', array_map('strval', $f->enumValues));
		}

		if (in_array($t, ['blob', 'anyblob', 'imageblob'], true)) {
			$rules[] = 'string';
			if (property_exists($f, 'minBytes') && $f->minBytes) {
				$rules[] = 'min:'.(int) $f->minBytes;
			}
			if (property_exists($f, 'maxBytes') && $f->maxBytes) {
				$rules[] = 'max:'.(int) $f->maxBytes;
			}
		}

		if (property_exists($f, 'unique') && $f->unique) {
			$rules[] = 'unique:'.$this->guessLaravelTable($fieldName).','.$fieldName;
		}

		return $rules;
	}

	private function guessLaravelTable(string $fieldName): string
	{
		return 'table';
	}
}
