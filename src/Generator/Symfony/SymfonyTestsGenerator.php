<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Generator\Symfony;

use CndApiMaker\Core\Definition\FieldDefinition;
use CndApiMaker\Core\Generator\GenerationContext;
use CndApiMaker\Core\Generator\Support\Naming;
use CndApiMaker\Core\Generator\Support\FieldTypeResolver;
use CndApiMaker\Core\Generator\Support\UniqueFieldPicker;
use CndApiMaker\Core\Renderer\StubRepository;
use CndApiMaker\Core\Renderer\TemplateRenderer;
use CndApiMaker\Core\Writer\FileWriter;

final readonly class SymfonyTestsGenerator
{
	public function __construct(
		private StubRepository $stubs,
		private TemplateRenderer $renderer,
		private FileWriter $writer,
		private UniqueFieldPicker $picker,
		private Naming $naming,
		private Naming $names,
		private FieldTypeResolver $types
	) {
	}

	public function generate(GenerationContext $ctx): array
	{
		$testsEnabled = (bool) ($ctx->def->features?->enabled('tests') ?? $ctx->def->tests->enabled ?? true);
		if (!$testsEnabled) {
			return [];
		}

		$files = [];
		$files = array_merge($files, $this->generateBaseWebTestCase($ctx));
		$files = array_merge($files, $this->generateApiTest($ctx));

		return $files;
	}

	private function generateBaseWebTestCase(GenerationContext $ctx): array
	{
		$tpl = $this->stubs->get('symfony/tests.support.base_api_test_case');

		$securityEnabled = (bool) ($ctx->def->features?->enabled('security') ?? $ctx->def->security?->enabled ?? false);

		$authHelpers = $securityEnabled ? implode("\n", [
			'    protected function loginAsUser(): string',
			'    {',
			'        return $this->login(UserFixtures::USER_EMAIL, UserFixtures::USER_PASSWORD);',
			'    }',
			'',
			'    protected function loginAsAdmin(): string',
			'    {',
			'        return $this->login(UserFixtures::ADMIN_EMAIL, UserFixtures::ADMIN_PASSWORD);',
			'    }',
		]) : '';

		$authSetup = $securityEnabled ? implode("\n", [
			'        $email = faker()->unique()->safeEmail();',
			'        $password = \'password\';',
			'',
			'        $user = UserFactory::createOne([',
			'            \'email\' => $email,',
			'            \'password\' => $password,',
			'        ]);',
			'',
			'        $this->token = $this->login($email, $password);',
			'        $this->user = $user;',
		]) : implode("\n", [
			'        $this->token = null;',
			'        $this->user = null;',
		]);

		$authHeaderLine = $securityEnabled
			? "        if (\$this->token) {\n            \$default['HTTP_Authorization'] = 'Bearer '.\$this->token;\n        }\n"
			: '';

		$loginMethod = $securityEnabled ? $this->loginMethodBlock() : '';

		$content = $this->renderer->render($tpl, [
			'securityEnabled' => $securityEnabled,
			'authSetup' => $authSetup,
			'authHeaderLine' => $authHeaderLine,
			'loginMethod' => $loginMethod,
			'authHelpers' => $authHelpers,
		]);

		$path = $ctx->path('tests/Shared/BaseWebTestCase.php');
		$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

		return [['path' => $path, 'type' => 'test-support']];
	}

	private function loginMethodBlock(): string
	{
		return implode("\n", [
			'    protected function login(string $email, string $password): string',
			'    {',
			'        $this->post(\'/api/v1/login\', [',
			'            \'email\' => $email,',
			'            \'password\' => $password,',
			'        ], [',
			'            \'CONTENT_TYPE\' => \'application/json\',',
			'            \'HTTP_ACCEPT\' => \'application/ld+json\',',
			'        ]);',
			'',
			'        $responseData = $this->getContent();',
			'',
			'        $token = $responseData[\'data\'][\'token\'] ?? null;',
			'        if (!is_string($token) || $token === \'\') {',
			'            throw new \\RuntimeException(\'Authentication token not found.\');',
			'        }',
			'',
			'        return $token;',
			'    }',
		]);
	}

	private function generateApiTest(GenerationContext $ctx): array
	{
		$tpl = $this->stubs->get('symfony/tests.api');

		$prefix = rtrim((string) ($ctx->def->api->uriPrefix ?? $ctx->uriPrefix), '/');
		$basePath = ($prefix !== '' ? $prefix : '');

		[$uniqueField, $uniqueExpr] = $this->picker->pickUniqueField($ctx->def->fields);

		$securityEnabled = (bool) ($ctx->def->features?->enabled('security') ?? $ctx->def->security?->enabled ?? false);
		$softDeletesEnabled = (bool) ($ctx->def->features?->enabled('softDeletes') ?? false);

		$authLine = $securityEnabled ? "        \$this->token = \$this->loginAsAdmin();\n" : '';

		$relationsSetupCreate = $this->relationsSetupOrSkip($ctx, true);
		$relationsSetupUpdate = $this->relationsSetupOrSkip($ctx, false);

		$createPayload = $this->payloadArray($ctx, $uniqueField, $uniqueExpr, true);
		$updatePayload = $this->payloadArray($ctx, $uniqueField, $uniqueExpr, false);

		$postDeleteChecks = $softDeletesEnabled ? implode("\n", [
			'        $this->get(\'{{basePath}}/\'.$id);',
			'        $this->assertTrue(in_array($this->response?->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_GONE], true));',
			'',
			'        $this->get(\'{{basePath}}\');',
			'        $this->assertResponseIsSuccessful();',
		]) : '';

		$content = $this->renderer->render($tpl, [
			'entity' => $ctx->entity,
			'entitySnake' => $this->naming->pluralize($ctx->entitySnake),
			'basePath' => $basePath,
			'uniqueField' => $uniqueField,
			'uniqueExpr' => $uniqueExpr,
			'createPayload' => $createPayload,
			'updatePayload' => $updatePayload,
			'relationsSetupCreate' => $relationsSetupCreate,
			'relationsSetupUpdate' => $relationsSetupUpdate,
			'authLine' => $authLine,
			'postDeleteChecks' => $postDeleteChecks,
		]);

		$path = $ctx->path('tests/ApiResource/'.$ctx->entity.'ApiTest.php');
		$this->writer->write($path, $content, $ctx->force, $ctx->dryRun);

		return [['path' => $path, 'type' => 'test']];
	}

	private function relationsSetupOrSkip(GenerationContext $ctx, bool $forCreate): string
	{
		$factoriesEnabled = (bool) ($ctx->def->features?->enabled('factories') ?? $ctx->def->features?->enabled('factory') ?? false);
		if ($factoriesEnabled) {
			return $this->relationsSetup($ctx, $forCreate);
		}

		$hasRequiredRelations = false;
		foreach ($ctx->def->fields as $f) {
			if ($f instanceof FieldDefinition && $f->relationKind !== null && $f->targetEntity !== null && $f->fillable) {
				if ($forCreate && !$f->nullable) {
					$hasRequiredRelations = true;
					break;
				}
			}
		}

		return $hasRequiredRelations
			? "        \$this->markTestSkipped('Factories disabled: cannot create required relations.');"
			: '';
	}

	private function relationsSetup(GenerationContext $ctx, bool $forCreate): string
	{
		$lines = [];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			if ($f->relationKind === null || $f->targetEntity === null) {
				continue;
			}

			if (!$f->fillable) {
				continue;
			}

			if ($forCreate && $f->nullable) {
				continue;
			}

			$prop = $this->names->camel((string) $f->name);
			$target = (string) $f->targetEntity;

			if ($f->isCollection) {
				$lines[] = "\${$prop}1 = \\App\\Factory\\{$target}Factory::createOne();";
				$lines[] = "\${$prop}2 = \\App\\Factory\\{$target}Factory::createOne();";
				$lines[] = "\${$prop}Ids = [\$this->idOf(\${$prop}1, ''), \$this->idOf(\${$prop}2, '')];";
				continue;
			}

			$lines[] = "\${$prop} = \\App\\Factory\\{$target}Factory::createOne();";
			$lines[] = "\${$prop}Id = \$this->idOf(\${$prop}, '');";
		}

		return implode("\n", $lines);
	}

	private function payloadArray(GenerationContext $ctx, string $uniqueField, string $uniqueExpr, bool $forCreate): string
	{
		$lines = [];
		$lines[] = '[';

		$seen = [];

		foreach ($ctx->def->fields as $f) {
			if (!$f instanceof FieldDefinition) {
				continue;
			}

			$name = (string) $f->name;
			if ($name === 'id') {
				continue;
			}

			$prop = $this->names->camel($name);
			$seen[$prop] = true;

			if ($f->relationKind !== null && $f->targetEntity !== null) {
				if (!$f->fillable) {
					continue;
				}

				if ($forCreate && $f->nullable) {
					continue;
				}

				$lines[] = $f->isCollection
					? "    '".$prop."' => \${$prop}Ids,"
					: "    '".$prop."' => \${$prop}Id,";
				continue;
			}

			$value = $prop === $uniqueField
				? $uniqueExpr
				: $this->valueExprFor($f, $prop, $forCreate);

			if ($value === null) {
				continue;
			}

			$lines[] = "    '".$prop."' => ".$value.",";
		}

		if (!isset($seen[$uniqueField])) {
			$lines[] = "    '".$uniqueField."' => ".$uniqueExpr.",";
		}

		$lines[] = ']';

		return implode("\n", $lines);
	}

	private function valueExprFor(FieldDefinition $f, string $prop, bool $forCreate): ?string
	{
		if ($forCreate && $f->nullable) {
			return null;
		}

		$t = strtolower((string) $f->type);

		if (in_array($t, ['blob', 'anyblob', 'imageblob', 'textblob'], true)) {
			return "'data:application/octet-stream;base64,'.base64_encode('BLOB-'.\$uuid)";
		}

		if (in_array($t, ['bool', 'boolean'], true)) {
			return 'true';
		}

		if (in_array($t, ['int', 'integer', 'long', 'bigint'], true)) {
			return '123';
		}

		if (in_array($t, ['float', 'double', 'decimal', 'bigdecimal'], true)) {
			return '12.3';
		}

		if ($this->types->isDateLike($f)) {
			return "'2026-01-01T00:00:00+00:00'";
		}

		if (in_array($t, ['uuid'], true)) {
			return "'00000000-0000-0000-0000-000000000000'";
		}

		return "'VAL-'.\$uuid";
	}
}
