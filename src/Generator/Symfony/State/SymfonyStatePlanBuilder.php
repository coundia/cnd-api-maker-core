<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony\State;

use CndApiMaker\Core\Generator\GenerationContext;

final readonly class SymfonyStatePlanBuilder
{
	public function __construct(
		private Base64FeatureResolver $base64,
		private MapperAssignmentsBuilder $mapperAssignments,
		private ApplyAssignmentsBuilder $applyAssignments,
		private RepositoryMethodsBuilder $repoMethods
	) {
	}

	public function build(GenerationContext $ctx): SymfonyStateFilesPlan
	{
		$entity = $ctx->entity;
		$stateBase = $ctx->path('src/State/'.$entity);
		$base64 = $this->base64->resolve($ctx);

		$softDeletes = (bool) ($ctx->def->features?->enabled('softDeletes') ?? false);
		$audit = (bool) ($ctx->def->features?->enabled('audit') ?? false);

		$repoFindAllCall = $softDeletes ? '$this->repository->findAllNotDeleted()' : '$this->repository->findAll()';
		$repoFindOneCall = $softDeletes ? '$this->repository->findOneNotDeleted($id)' : '$this->repository->find($id)';

		$deleteLogic = $softDeletes
			? implode("\n", [
				'        $entity->softDelete();',
				'        $this->em->flush();',
			])
			: implode("\n", [
				'        $this->em->remove($entity);',
				'        $this->em->flush();',
			]);

		$auditTouchWrite = $audit
			? implode("\n", [
				'        $now = new \\DateTimeImmutable();',
				'        if (property_exists($entity, \'createdAt\') && $entity->createdAt === null) {',
				'            $entity->createdAt = $now;',
				'        }',
				'        if (property_exists($entity, \'updatedAt\')) {',
				'            $entity->updatedAt = $now;',
				'        }',
			])."\n"
			: '';

		return new SymfonyStateFilesPlan(
			repoTpl: 'symfony/state.repository',
			repoVars: [
				'entity' => $entity,
				'repositoryMethods' => $this->repoMethods->build($ctx, $entity),
			],
			repoPath: $ctx->path('src/Repository/'.$entity.'Repository.php'),

			mapperTpl: 'symfony/state.mapper',
			mapperVars: [
				'entity' => $entity,
				'base64Uses' => $base64->uses,
				'base64CtorArg' => $base64->ctorArg,
				'base64ApplyArg' => $base64->applyArg,
				'mapperAssignments' => $this->mapperAssignments->build($ctx),
				'applyAssignments' => $this->applyAssignments->build($ctx, $base64->enabled),
			],
			mapperPath: $stateBase.'/'.$entity.'Mapper.php',

			payloadTpl: 'symfony/state.payload_resolver',
			payloadVars: ['entity' => $entity],
			payloadPath: $stateBase.'/'.$entity.'PayloadResolver.php',

			collectionTpl: 'symfony/state.collection_provider',
			collectionVars: [
				'entity' => $entity,
				'repoFindAllCall' => $repoFindAllCall,
			],
			collectionPath: $stateBase.'/'.$entity.'CollectionProvider.php',

			itemTpl: 'symfony/state.item_provider',
			itemVars: [
				'entity' => $entity,
				'repoFindOneCall' => $repoFindOneCall,
			],
			itemPath: $stateBase.'/'.$entity.'ItemProvider.php',

			writeTpl: 'symfony/state.write_processor',
			writeVars: [
				'entity' => $entity,
				'base64Uses' => $base64->uses,
				'base64CtorArg' => $base64->ctorArg,
				'base64ApplyArg' => $base64->applyArg,
				'repoFindOneCall' => $repoFindOneCall,
				'auditTouchWrite' => $auditTouchWrite,
			],
			writePath: $stateBase.'/'.$entity.'WriteProcessor.php',

			deleteTpl: 'symfony/state.delete_processor',
			deleteVars: [
				'entity' => $entity,
				'repoFindOneCall' => $repoFindOneCall,
				'deleteLogic' => $deleteLogic,
			],
			deletePath: $stateBase.'/'.$entity.'DeleteProcessor.php'
		);
	}
}
