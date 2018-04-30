<?php
namespace benf\neo\models;

use Craft;
use craft\base\Model;

class BlockStructure extends Model
{
	public $id;
	public $structureId;
	public $fieldId;
	public $ownerId;
	public $ownerSiteId;

	private $_structure;

	public function rules()
	{
		return [
			[['id', 'structureId', 'fieldId', 'ownerId', 'ownerSiteId' ], 'number', 'integerOnly' => true],
		];
	}

	public function getStructure()
	{
		$structuresService = Craft::$app->getStructures();

		if (!$this->_structure && $this->structureId)
		{
			$this->_structure = $structuresService->getStructureById($this->structureId);
		}

		return $this->_structure;
	}
}
