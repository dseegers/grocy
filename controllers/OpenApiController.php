<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OpenApiController extends BaseApiController
{
	public function ApiKeysList(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		$apiKeys = $this->getDatabase()->api_keys();
		if (!User::hasPermissions(User::PERMISSION_ADMIN))
		{
			$apiKeys = $apiKeys->where('user_id', GROCY_USER_ID);
		}
		return $this->renderPage($response, 'manageapikeys', [
			'apiKeys' => $apiKeys,
			'users' => $this->getDatabase()->users()
		]);
	}

	public function CreateNewApiKey(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		$newApiKey = $this->getApiKeyService()->CreateApiKey();
		$newApiKeyId = $this->getApiKeyService()->GetApiKeyId($newApiKey);
		return $response->withRedirect($this->AppContainer->get('UrlManager')->ConstructUrl("/manageapikeys?CreatedApiKeyId=$newApiKeyId"));
	}

	public function DocumentationSpec(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		$spec = $this->getOpenApiSpec();

		$applicationService = $this->getApplicationService();
		$versionInfo = $applicationService->GetInstalledVersion();
		$spec->info->version = $versionInfo->Version;
		$spec->info->description = str_replace('PlaceHolderManageApiKeysUrl', $this->AppContainer->get('UrlManager')->ConstructUrl('/manageapikeys'), $spec->info->description);
		$spec->servers[0]->url = $this->AppContainer->get('UrlManager')->ConstructUrl('/api');

		$spec->components->schemas->ExposedEntity_IncludingUserEntities = clone $spec->components->schemas->ExposedEntity;
		foreach ($this->getUserfieldsService()->GetEntities() as $userEntity)
		{
			array_push($spec->components->schemas->ExposedEntity_IncludingUserEntities->enum, $userEntity);
		}
		sort($spec->components->schemas->ExposedEntity_IncludingUserEntities->enum);

		$spec->components->schemas->ExposedEntity_NotIncludingNotEditable = clone $spec->components->schemas->StringEnumTemplate;
		foreach ($spec->components->schemas->ExposedEntity->enum as $value)
		{
			if (!in_array($value, $spec->components->schemas->ExposedEntityNoEdit->enum))
			{
				array_push($spec->components->schemas->ExposedEntity_NotIncludingNotEditable->enum, $value);
			}
		}
		sort($spec->components->schemas->ExposedEntity_NotIncludingNotEditable->enum);

		$spec->components->schemas->ExposedEntity_IncludingUserEntities_NotIncludingNotEditable = clone $spec->components->schemas->StringEnumTemplate;
		foreach ($spec->components->schemas->ExposedEntity_IncludingUserEntities->enum as $value)
		{
			if (!in_array($value, $spec->components->schemas->ExposedEntityNoEdit->enum))
			{
				array_push($spec->components->schemas->ExposedEntity_IncludingUserEntities_NotIncludingNotEditable->enum, $value);
			}
		}
		sort($spec->components->schemas->ExposedEntity_IncludingUserEntities_NotIncludingNotEditable->enum);

		$spec->components->schemas->ExposedEntity_NotIncludingNotDeletable = clone $spec->components->schemas->StringEnumTemplate;
		foreach ($spec->components->schemas->ExposedEntity->enum as $value)
		{
			if (!in_array($value, $spec->components->schemas->ExposedEntityNoDelete->enum))
			{
				array_push($spec->components->schemas->ExposedEntity_NotIncludingNotDeletable->enum, $value);
			}
		}
		sort($spec->components->schemas->ExposedEntity_NotIncludingNotDeletable->enum);

		$spec->components->schemas->ExposedEntity_NotIncludingNotListable = clone $spec->components->schemas->StringEnumTemplate;
		foreach ($spec->components->schemas->ExposedEntity->enum as $value)
		{
			if (!in_array($value, $spec->components->schemas->ExposedEntityNoListing->enum))
			{
				array_push($spec->components->schemas->ExposedEntity_NotIncludingNotListable->enum, $value);
			}
		}
		sort($spec->components->schemas->ExposedEntity_NotIncludingNotListable->enum);

		return $this->ApiResponse($response, $spec);
	}

	public function DocumentationUi(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		return $this->render($response, 'openapiui');
	}
}
