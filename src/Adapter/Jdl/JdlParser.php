<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Adapter\Jdl;

final class JdlParser
{
	public function parse(string $content): JdlDocument
	{
		$src = $this->stripComments($content);

		$config = $this->parseConfig($src);
		$entities = $this->parseEntities($src);
		$enums = $this->parseEnums($src);
		$relations = $this->parseRelations($src);

		return new JdlDocument($config, $entities, $enums, $relations);
	}

	private function stripComments(string $s): string
	{
		$s = preg_replace('~/\*\*.*?\*/~s', '', $s) ?? $s;
		$s = preg_replace('~/\*.*?\*/~s', '', $s) ?? $s;
		$s = preg_replace('~//.*$~m', '', $s) ?? $s;
		return $s;
	}

	/** @return array<string, JdlEntity> */
	private function parseEntities(string $src): array
	{
		$out = [];

		if (!preg_match_all('~\bentity\s+([A-Za-z_][A-Za-z0-9_]*)\s*\{(.*?)\}~s', $src, $m, PREG_SET_ORDER)) {
			return $out;
		}

		foreach ($m as $mm) {
			$name = trim((string) $mm[1]);
			$body = (string) $mm[2];

			$fields = $this->parseEntityFields($body);
			$out[$name] = new JdlEntity($name, $fields);
		}

		return $out;
	}

	/** @return JdlField[] */
	private function parseEntityFields(string $body): array
	{
		$lines = preg_split('~\R~', $body) ?: [];
		$fields = [];

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (!preg_match('~^([A-Za-z_][A-Za-z0-9_]*)\s+([A-Za-z_][A-Za-z0-9_]*)\s*(required)?\s*$~', $line, $mm)) {
				continue;
			}

			$fname = (string) $mm[1];
			$ftype = (string) $mm[2];
			$required = isset($mm[3]) && $mm[3] === 'required';

			$fields[] = new JdlField($fname, $ftype, $required);
		}

		return $fields;
	}

	/** @return array<string, JdlEnum> */
	private function parseEnums(string $src): array
	{
		$out = [];

		if (!preg_match_all('~\benum\s+([A-Za-z_][A-Za-z0-9_]*)\s*\{(.*?)\}~s', $src, $m, PREG_SET_ORDER)) {
			return $out;
		}

		foreach ($m as $mm) {
			$name = trim((string) $mm[1]);
			$body = (string) $mm[2];

			$vals = array_values(array_filter(array_map(
				static fn (string $v) => trim($v),
				preg_split('~\s*,\s*~', trim($body)) ?: []
			), static fn (string $v) => $v !== ''));

			$out[$name] = new JdlEnum($name, $vals);
		}

		return $out;
	}

	/** @return JdlRelation[] */
	private function parseRelations(string $src): array
	{
		$rels = [];

		$len = strlen($src);
		$pos = 0;

		while ($pos < $len) {
			if (!preg_match('~\brelationship\s+(OneToOne|OneToMany|ManyToOne|ManyToMany)\s*\{~A', $src, $m, 0, $pos)) {
				$next = strpos($src, 'relationship', $pos);
				if ($next === false) {
					break;
				}
				$pos = $next;
				continue;
			}

			$kind = (string) $m[1];
			$openPos = $pos + strlen($m[0]) - 1;

			[$body, $endPos] = $this->readBalancedBracesBody($src, $openPos);
			$pos = $endPos;

			$lines = preg_split('~\R~', trim($body)) ?: [];
			$lines = array_values(array_filter(array_map('trim', $lines), static fn ($v) => $v !== ''));

			foreach ($lines as $line) {
				$rel = $this->parseRelationLine($kind, $line);
				if ($rel !== null) {
					$rels[] = $rel;
				}
			}
		}

		return $rels;
	}

	/** @return array{0:string,1:int} */
	private function readBalancedBracesBody(string $src, int $openBracePos): array
	{
		$len = strlen($src);
		$depth = 0;
		$i = $openBracePos;
		$start = null;

		for (; $i < $len; $i++) {
			$ch = $src[$i];

			if ($ch === '{') {
				$depth++;
				if ($depth === 1) {
					$start = $i + 1;
				}
				continue;
			}

			if ($ch === '}') {
				$depth--;
				if ($depth === 0 && $start !== null) {
					$body = substr($src, $start, $i - $start);
					return [$body, $i + 1];
				}
			}
		}

		return ['', $len];
	}

	private function parseRelationLine(string $kind, string $line): ?JdlRelation
	{
		if (!preg_match('~^([A-Za-z_][A-Za-z0-9_]*)(\{([^}]+)\})?\s+to\s+([A-Za-z_][A-Za-z0-9_]*)(\{([^}]+)\})?\s*$~', $line, $m)) {
			return null;
		}

		$fromEntity = (string) $m[1];
		$fromField = isset($m[3]) ? $this->extractFieldName((string) $m[3]) : null;

		$toEntity = (string) $m[4];
		$toField = isset($m[6]) ? $this->extractFieldName((string) $m[6]) : null;

		return new JdlRelation($kind, $fromEntity, $fromField, $toEntity, $toField);
	}

	private function extractFieldName(string $raw): string
	{
		$raw = trim($raw);
		if (preg_match('~^([A-Za-z_][A-Za-z0-9_]*)~', $raw, $m)) {
			return (string) $m[1];
		}
		return $raw;
	}

	private function parseConfig(string $src): JdlConfig
	{
		if (!preg_match('~\bconfig\s*\{(.*?)\}~s', $src, $m)) {
			return new JdlConfig();
		}

		$body = trim($m[1]);
		$cfg = new JdlConfig();

		foreach (preg_split('~\R~', $body) ?: [] as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('~^(framework|driver|uriPrefix)\s+(.+)$~', $line, $mm)) {
				$cfg->{$mm[1]} = trim($mm[2]);
				continue;
			}

			if (preg_match('~^(uuid)\s+(true|false)$~', $line, $mm)) {
				$cfg->uuid = $mm[2] === 'true';
				continue;
			}

			if (preg_match('~features\s*\{~', $line)) {
				continue;
			}

			if (preg_match('~^(tenant|softDeletes|audit|factory)\s+(true|false)$~', $line, $mm)) {
				$cfg->{$mm[1]} = $mm[2] === 'true';
			}
		}

		return $cfg;
	}
}
