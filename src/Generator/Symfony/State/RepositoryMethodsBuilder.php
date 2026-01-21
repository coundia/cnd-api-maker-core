<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Generator\GenerationContext;

final readonly class RepositoryMethodsBuilder
{
	public function build(GenerationContext $ctx, string $entity): string
	{
		$softDeletes = (bool) ($ctx->def->features?->enabled('softDeletes') ?? false);

//		dd($softDeletes);

		if (!$softDeletes) {
			return '';
		}

		return implode("\n", [
			'	public function findAllNotDeleted(): array',
			'	{',
			"		return \$this->createQueryBuilder('e')",
			"			->andWhere('e.deletedAt IS NULL')",
			'			->getQuery()',
			'			->getResult();',
			'	}',
			'',
			'	public function findOneNotDeleted(string $id): ?'.$entity,
			'	{',
			"		return \$this->createQueryBuilder('e')",
			"			->andWhere('e.id = :id')",
			"			->andWhere('e.deletedAt IS NULL')",
			'			->setParameter(\'id\', $id)',
			'			->getQuery()',
			'			->getOneOrNullResult();',
			'	}',
		]);
	}
}
