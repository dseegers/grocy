<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

class GenericEntityApiController extends BaseApiController
{
	public function AddObject(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		if ($this->IsValidExposedEntity($args['entity']) && !$this->IsEntityWithNoEdit($args['entity']))
		{
			if ($this->IsEntityWithEditRequiresAdmin($args['entity']))
			{
				User::checkPermission($request, User::PERMISSION_ADMIN);
			}

			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			try
			{
				if ($requestBody === null)
				{
					throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
				}

				$newRow = $this->getDatabase()->{$args['entity']}()->createRow($requestBody);
				$newRow->save();
				$success = $newRow->isClean();

				return $this->ApiResponse($response, [
					'created_object_id' => $this->getDatabase()->lastInsertId()
				]);
			}
			catch (\Exception $ex)
			{
				return $this->GenericErrorResponse($response, $ex->getMessage());
			}
		}
		else
		{
			return $this->GenericErrorResponse($response, 'Entity does not exist or is not exposed');
		}
	}

	public function DeleteObject(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		if ($this->IsValidExposedEntity($args['entity']) && !$this->IsEntityWithNoDelete($args['entity']))
		{
			if ($this->IsEntityWithEditRequiresAdmin($args['entity']))
			{
				User::checkPermission($request, User::PERMISSION_ADMIN);
			}

			$row = $this->getDatabase()->{$args['entity']}($args['objectId']);
			if ($row == null)
			{
				return $this->GenericErrorResponse($response, 'Object not found', 400);
			}

			$row->delete();
			$success = $row->isClean();

			return $this->EmptyApiResponse($response);
		}
		else
		{
			return $this->GenericErrorResponse($response, 'Invalid entity');
		}
	}

	public function EditObject(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		if ($this->IsValidExposedEntity($args['entity']) && !$this->IsEntityWithNoEdit($args['entity']))
		{
			if ($this->IsEntityWithEditRequiresAdmin($args['entity']))
			{
				User::checkPermission($request, User::PERMISSION_ADMIN);
			}

			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			try
			{
				if ($requestBody === null)
				{
					throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
				}

				$row = $this->getDatabase()->{$args['entity']}($args['objectId']);
				if ($row == null)
				{
					return $this->GenericErrorResponse($response, 'Object not found', 400);
				}

				$row->update($requestBody);
				$success = $row->isClean();

				return $this->EmptyApiResponse($response);
			}
			catch (\Exception $ex)
			{
				return $this->GenericErrorResponse($response, $ex->getMessage());
			}
		}
		else
		{
			return $this->GenericErrorResponse($response, 'Entity does not exist or is not exposed');
		}
	}

	public function GetObject(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		if ($this->IsValidExposedEntity($args['entity']) && !$this->IsEntityWithNoListing($args['entity']))
		{
			$userfields = $this->getUserfieldsService()->GetValues($args['entity'], $args['objectId']);
			if (count($userfields) === 0)
			{
				$userfields = null;
			}

			$object = $this->getDatabase()->{$args['entity']}($args['objectId']);
			if ($object == null)
			{
				return $this->GenericErrorResponse($response, 'Object not found', 404);
			}

			$object['userfields'] = $userfields;

			return $this->ApiResponse($response, $object);
		}
		else
		{
			return $this->GenericErrorResponse($response, 'Entity does not exist or is not exposed');
		}
	}

	public function GetObjects(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		if (!$this->IsValidExposedEntity($args['entity']) || $this->IsEntityWithNoListing($args['entity']))
		{
			return $this->GenericErrorResponse($response, 'Entity does not exist or is not exposed');
		}

		$objects = $this->queryData($this->getDatabase()->{$args['entity']}(), $request->getQueryParams());
		$userfields = $this->getUserfieldsService()->GetFields($args['entity']);

		if (count($userfields) > 0)
		{
			$allUserfieldValues = $this->getUserfieldsService()->GetAllValues($args['entity']);

			foreach ($objects as $object)
			{
				$userfieldKeyValuePairs = null;
				foreach ($userfields as $userfield)
				{
					$value = FindObjectInArrayByPropertyValue(FindAllObjectsInArrayByPropertyValue($allUserfieldValues, 'object_id', $object->id), 'name', $userfield->name);
					if ($value)
					{
						$userfieldKeyValuePairs[$userfield->name] = $value->value;
					}
					else
					{
						$userfieldKeyValuePairs[$userfield->name] = null;
					}
				}

				$object->userfields = $userfieldKeyValuePairs;
			}
		}

		return $this->ApiResponse($response, $objects);
	}

	public function GetUserfields(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		try
		{
			return $this->ApiResponse($response, $this->getUserfieldsService()->GetValues($args['entity'], $args['objectId']));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SetUserfields(ServerRequestInterface $request, ResponseInterface $response, array $args)
	{
		User::checkPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		$requestBody = $this->GetParsedAndFilteredRequestBody($request);

		try
		{
			if ($requestBody === null)
			{
				throw new \Exception('Request body could not be parsed (probably invalid JSON format or missing/wrong Content-Type header)');
			}

			$this->getUserfieldsService()->SetValues($args['entity'], $args['objectId'], $requestBody);
			return $this->EmptyApiResponse($response);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	private function IsEntityWithEditRequiresAdmin($entity)
	{
		return in_array($entity, $this->getOpenApiSpec()->components->schemas->ExposedEntityEditRequiresAdmin->enum);
	}

	private function IsEntityWithNoListing($entity)
	{
		return in_array($entity, $this->getOpenApiSpec()->components->schemas->ExposedEntityNoListing->enum);
	}

	private function IsEntityWithNoEdit($entity)
	{
		return in_array($entity, $this->getOpenApiSpec()->components->schemas->ExposedEntityNoEdit->enum);
	}

	private function IsEntityWithNoDelete($entity)
	{
		return in_array($entity, $this->getOpenApiSpec()->components->schemas->ExposedEntityNoDelete->enum);
	}

	private function IsValidExposedEntity($entity)
	{
		return in_array($entity, $this->getOpenApiSpec()->components->schemas->ExposedEntity->enum);
	}
}
