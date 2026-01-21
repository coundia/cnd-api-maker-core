<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;
final class Config
{
	public static function applyGlobalDefaults(object $def, array $config): void
	{

		self::mergeDefaultsIntoObject($def, $config);

	}

	private static function mergeDefaultsIntoObject(object $target, array $defaults): void
	{
		foreach ($defaults as $key => $value) {
			$k = (string) $key;

			if (is_array($value) && self::isAssoc($value)) {
				if (!property_exists($target, $k) || $target->{$k} === null) {
					$target->{$k} = (object) [];
				} elseif (!is_object($target->{$k})) {
					continue;
				}

				self::mergeDefaultsIntoObject($target->{$k}, $value);
				continue;
			}

			if (!property_exists($target, $k) || $target->{$k} === null) {
				$target->{$k} = $value;
			}
		}
	}

	private static function isAssoc(array $arr): bool
	{
		if ($arr === []) {
			return false;
		}

		$i = 0;
		foreach (array_keys($arr) as $k) {
			if ($k !== $i) {
				return true;
			}
			$i++;
		}

		return false;
	}
}
