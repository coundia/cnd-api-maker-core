<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;

final class Base64FeatureResolver
{
	public function resolve(GenerationContext $ctx): Base64Feature
	{
		$enabled = $this->hasBase64($ctx);

		return new Base64Feature(
			enabled: $enabled,
			uses: $enabled ? "use App\\Service\\Base64FileService;\n" : '',
			ctorArg: $enabled ? ",\n        private Base64FileService \$base64Files" : '',
			applyArg: $enabled ? ', $this->base64Files' : ''
		);
	}

	private function hasBase64(GenerationContext $ctx): bool
	{
		foreach ($ctx->def->fields as $f) {
			if ($f instanceof FieldDefinition && $f->relationKind === null && $this->isBase64Like($f)) {
				return true;
			}
		}
		return false;
	}

	private function isBase64Like(FieldDefinition $f): bool
	{
		$t = strtolower((string) $f->type);
		return in_array($t, ['blob', 'anyblob', 'imageblob', 'textblob'], true);
	}
}
